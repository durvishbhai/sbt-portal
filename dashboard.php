<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();
$pageTitle = 'Dashboard';

if (is_admin($user)) {
    $stats = [
        'total_users' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'pending_users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn(),
        'total_teams' => (int) $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn(),
        'pending_teams' => (int) $pdo->query("SELECT COUNT(*) FROM teams WHERE status='pending'")->fetchColumn(),
        'open_complaints' => (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('open','in_review')")->fetchColumn(),
        'pending_documents' => (int) $pdo->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn(),
        'active_elections' => (int) $pdo->query("SELECT COUNT(*) FROM elections WHERE status='active'")->fetchColumn(),
        'coins_in_circulation' => (float) $pdo->query("SELECT COALESCE(SUM(balance),0) FROM wallet_accounts")->fetchColumn(),
    ];

    $roleDistribution = $pdo->query(
        "SELECT r.name, COUNT(u.id) AS total FROM roles r
         LEFT JOIN users u ON u.role_id = r.id
         GROUP BY r.id ORDER BY r.sort_order"
    )->fetchAll();
    $maxRoleTotal = max(1, max(array_column($roleDistribution, 'total')));

    $recentActivity = $pdo->query(
        "SELECT a.action, a.details, a.created_at, u.full_name
         FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT 8"
    )->fetchAll();
}

$myWallet = $pdo->prepare('SELECT balance FROM wallet_accounts WHERE user_id = ?');
$myWallet->execute([$user['id']]);
$walletBalance = (float) ($myWallet->fetchColumn() ?: 0);

$myDocs = $pdo->prepare('SELECT status, COUNT(*) c FROM documents WHERE user_id = ? GROUP BY status');
$myDocs->execute([$user['id']]);
$myDocStatus = array_column($myDocs->fetchAll(), 'c', 'status');

$stmtMyComplaints = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE submitted_by = ?');
$stmtMyComplaints->execute([$user['id']]);
$myComplaintsCount = (int) $stmtMyComplaints->fetchColumn();

$activeElections = $pdo->query("SELECT COUNT(*) FROM elections WHERE status='active'")->fetchColumn();

$unreadMessages = $pdo->prepare(
    "SELECT COUNT(*) FROM messages m
     WHERE (m.recipient_id = ? OR (m.team_id = ? AND m.team_id IS NOT NULL) OR m.is_broadcast = 1)
       AND m.sender_id != ?
       AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)"
);
$unreadMessages->execute([$user['id'], $user['team_id'], $user['id'], $user['id']]);
$unreadCount = (int) $unreadMessages->fetchColumn();

require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Welcome, <?= e($user['full_name']) ?></h1>
    <p class="subtitle"><?= e($user['role_name']) ?> &middot; SBT Account <?= e($user['sbt_account_no']) ?></p>
  </div>
  <button class="btn btn-secondary btn-sm no-print" data-print>Print dashboard</button>
</div>

<div class="grid grid-4">
  <div class="stat-tile">
    <div class="stat-value"><?= format_coins($walletBalance) ?></div>
    <div class="stat-label">My SBT Coins</div>
  </div>
  <div class="stat-tile">
    <div class="stat-value"><?= (int) ($myDocStatus['verified'] ?? 0) ?>/<?= array_sum($myDocStatus) ?></div>
    <div class="stat-label">Documents Verified</div>
  </div>
  <div class="stat-tile">
    <div class="stat-value"><?= $myComplaintsCount ?></div>
    <div class="stat-label">My Complaints</div>
  </div>
  <div class="stat-tile">
    <div class="stat-value"><?= $unreadCount ?></div>
    <div class="stat-label">Unread Messages</div>
  </div>
</div>

<?php if ($activeElections > 0): ?>
<div class="alert alert-info">🗳️ There <?= $activeElections == 1 ? 'is' : 'are' ?> <?= $activeElections ?> active election<?= $activeElections == 1 ? '' : 's' ?> open for voting. <a href="<?= base_path() ?>/elections.php">Vote now &rarr;</a></div>
<?php endif; ?>

<?php if (is_admin($user)): ?>
<div class="grid grid-4">
  <div class="stat-tile">
    <div class="stat-value"><?= $stats['total_users'] ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-tile" style="border-left-color:#b8860b;">
    <div class="stat-value"><?= $stats['pending_users'] ?></div>
    <div class="stat-label">Pending Approval</div>
  </div>
  <div class="stat-tile">
    <div class="stat-value"><?= $stats['total_teams'] ?></div>
    <div class="stat-label">Teams (<?= $stats['pending_teams'] ?> pending)</div>
  </div>
  <div class="stat-tile" style="border-left-color:#d0342c;">
    <div class="stat-value"><?= $stats['open_complaints'] ?></div>
    <div class="stat-label">Open Complaints</div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Users by role</h3>
    <?php foreach ($roleDistribution as $row): ?>
      <div style="margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:3px;">
          <span><?= e($row['name']) ?></span><span><?= (int) $row['total'] ?></span>
        </div>
        <div style="background:#e6e8eb; border-radius:6px; height:8px;">
          <div style="background:#4caf1f; width:<?= (int) $row['total'] / $maxRoleTotal * 100 ?>%; height:8px; border-radius:6px;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Recent activity</h3>
    <?php if (!$recentActivity): ?>
      <p class="empty-state">No activity recorded yet.</p>
    <?php else: ?>
      <table>
        <?php foreach ($recentActivity as $row): ?>
          <tr>
            <td>
              <strong><?= e($row['full_name'] ?? 'System') ?></strong><br>
              <span style="color:#6b7280;font-size:12px;"><?= e($row['action']) ?><?= $row['details'] ? ' — ' . e($row['details']) : '' ?></span>
            </td>
            <td style="white-space:nowrap;color:#6b7280;font-size:12px;"><?= format_datetime($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <a href="<?= base_path() ?>/admin/logs.php" class="btn btn-sm btn-secondary">View full logs</a>
  </div>
</div>

<div class="card">
  <h3>Pending approvals</h3>
  <div class="grid grid-3">
    <a class="btn" href="<?= base_path() ?>/admin/users.php?status=pending">Pending users (<?= $stats['pending_users'] ?>)</a>
    <a class="btn" href="<?= base_path() ?>/teams.php?status=pending">Pending teams (<?= $stats['pending_teams'] ?>)</a>
    <a class="btn" href="<?= base_path() ?>/documents.php?status=pending">Pending documents (<?= $stats['pending_documents'] ?>)</a>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
