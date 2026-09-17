<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    require_role(ADMIN_ROLES);
    verify_csrf();

    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $type = $_POST['type'] === 'debit' ? 'debit' : 'credit';
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $reason = trim($_POST['reason'] ?? '');

    if ($amount <= 0 || $reason === '') {
        flash('error', 'Enter a positive amount and a reason.');
        redirect('wallet.php');
    }

    $targetStmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $targetStmt->execute([$targetUserId]);
    if (!$targetStmt->fetch()) {
        flash('error', 'User not found.');
        redirect('wallet.php');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO wallet_accounts (user_id, balance) VALUES (?, 0) ON DUPLICATE KEY UPDATE user_id = user_id')
            ->execute([$targetUserId]);

        if ($type === 'debit') {
            $balStmt = $pdo->prepare('SELECT balance FROM wallet_accounts WHERE user_id = ? FOR UPDATE');
            $balStmt->execute([$targetUserId]);
            $currentBalance = (float) $balStmt->fetchColumn();
            if ($currentBalance < $amount) {
                $pdo->rollBack();
                flash('error', 'Insufficient balance for this debit.');
                redirect('wallet.php');
            }
            $pdo->prepare('UPDATE wallet_accounts SET balance = balance - ? WHERE user_id = ?')->execute([$amount, $targetUserId]);
        } else {
            $pdo->prepare('UPDATE wallet_accounts SET balance = balance + ? WHERE user_id = ?')->execute([$amount, $targetUserId]);
        }

        $pdo->prepare('INSERT INTO wallet_transactions (user_id, type, amount, reason, created_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$targetUserId, $type, $amount, $reason, $user['id']]);
        $pdo->commit();
        log_activity($user['id'], 'wallet_' . $type, 'User #' . $targetUserId . ': ' . $amount . ' — ' . $reason);
        flash('success', 'Wallet updated.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    redirect('wallet.php');
}

$balStmt = $pdo->prepare('SELECT balance FROM wallet_accounts WHERE user_id = ?');
$balStmt->execute([$user['id']]);
$balance = (float) ($balStmt->fetchColumn() ?: 0);

$txStmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$txStmt->execute([$user['id']]);
$transactions = $txStmt->fetchAll();

$members = [];
if (is_admin($user)) {
    $members = $pdo->query(
        "SELECT u.id, u.full_name, w.balance FROM users u
         LEFT JOIN wallet_accounts w ON w.user_id = u.id
         WHERE u.status = 'active' ORDER BY u.full_name"
    )->fetchAll();
}

$pageTitle = 'SBT Coins Wallet';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>SBT Coins Wallet</h1>
    <p class="subtitle">Track your balance and transaction history.</p>
  </div>
  <?php if (!is_admin($user)): ?>
  <button class="btn btn-secondary btn-sm no-print" data-print>Print statement</button>
  <?php endif; ?>
</div>

<div class="stat-tile" style="max-width:260px;">
  <div class="stat-value"><?= format_coins($balance) ?></div>
  <div class="stat-label">My Balance (SBT Coins)</div>
</div>

<div class="card">
  <h3>Transaction history</h3>
  <div class="table-wrap">
  <?php if (!$transactions): ?>
    <p class="empty-state">No transactions yet.</p>
  <?php else: ?>
  <table>
    <tr><th>Date</th><th>Type</th><th>Amount</th><th>Reason</th></tr>
    <?php foreach ($transactions as $tx): ?>
      <tr>
        <td><?= format_datetime($tx['created_at']) ?></td>
        <td><span class="badge badge-<?= e($tx['type']) ?>"><?= e(ucfirst($tx['type'])) ?></span></td>
        <td><?= $tx['type'] === 'credit' ? '+' : '-' ?><?= format_coins((float) $tx['amount']) ?></td>
        <td><?= e($tx['reason']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>

<?php if (is_admin($user)): ?>
<div class="card no-print">
  <h3>Award / deduct SBT Coins</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="adjust">
    <label for="user_id">Member</label>
    <select id="user_id" name="user_id" required>
      <?php foreach ($members as $m): ?>
        <option value="<?= (int) $m['id'] ?>"><?= e($m['full_name']) ?> (<?= format_coins((float) ($m['balance'] ?? 0)) ?>)</option>
      <?php endforeach; ?>
    </select>
    <div class="field-row">
      <div>
        <label for="type">Type</label>
        <select id="type" name="type">
          <option value="credit">Credit (add)</option>
          <option value="debit">Debit (deduct)</option>
        </select>
      </div>
      <div>
        <label for="amount">Amount</label>
        <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>
      </div>
    </div>
    <label for="reason">Reason</label>
    <input type="text" id="reason" name="reason" required>
    <button type="submit" class="btn">Update wallet</button>
  </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
