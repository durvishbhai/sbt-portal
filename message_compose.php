<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_write_access();
$pdo = db();

$canBroadcastSite = is_admin($user);
$canBroadcastTeam = is_admin($user) || in_array($user['role_slug'], ['tm', 'other_team_maker'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $sendType = $_POST['send_type'] ?? 'direct';
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($body === '') {
        flash('error', 'Message body cannot be empty.');
        redirect('message_compose.php');
    }

    $recipientId = null;
    $teamId = null;
    $isBroadcast = 0;

    if ($sendType === 'broadcast' && $canBroadcastSite) {
        $isBroadcast = 1;
    } elseif ($sendType === 'team' && $canBroadcastTeam && $user['team_id']) {
        $teamId = $user['team_id'];
    } elseif ($sendType === 'direct') {
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND status = "active"');
        $checkStmt->execute([$recipientId]);
        if (!$checkStmt->fetch()) {
            flash('error', 'Please choose a valid recipient.');
            redirect('message_compose.php');
        }
    } else {
        flash('error', 'You do not have permission to send that type of message.');
        redirect('message_compose.php');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO messages (sender_id, recipient_id, team_id, is_broadcast, subject, body) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'], $recipientId, $teamId, $isBroadcast, $subject ?: null, $body]);
    log_activity($user['id'], 'message_sent', $isBroadcast ? 'Broadcast' : ($teamId ? 'Team message' : 'Direct message'));
    flash('success', 'Message sent.');
    redirect('messages.php?box=sent');
}

$recipients = $pdo->prepare('SELECT id, full_name, sbt_account_no FROM users WHERE status = "active" AND id != ? ORDER BY full_name');
$recipients->execute([$user['id']]);
$recipients = $recipients->fetchAll();

$pageTitle = 'Compose Message';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div><h1>Compose Message</h1></div></div>

<div class="card" style="max-width:560px;">
  <form method="post">
    <?= csrf_field() ?>
    <label for="send_type">Send to</label>
    <select id="send_type" name="send_type" onchange="document.getElementById('recipientField').style.display = this.value==='direct' ? 'block':'none';">
      <option value="direct">A specific member</option>
      <?php if ($canBroadcastTeam && $user['team_id']): ?><option value="team">My team (group announcement)</option><?php endif; ?>
      <?php if ($canBroadcastSite): ?><option value="broadcast">Everyone (site-wide broadcast)</option><?php endif; ?>
    </select>

    <div id="recipientField">
      <label for="recipient_id">Recipient</label>
      <select id="recipient_id" name="recipient_id">
        <?php foreach ($recipients as $r): ?>
          <option value="<?= (int) $r['id'] ?>"><?= e($r['full_name']) ?> (<?= e($r['sbt_account_no']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <label for="subject">Subject (optional)</label>
    <input type="text" id="subject" name="subject">

    <label for="body">Message</label>
    <textarea id="body" name="body" required></textarea>

    <button type="submit" class="btn">Send</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
