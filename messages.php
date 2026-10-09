<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();
$box = $_GET['box'] ?? 'inbox';

if ($box === 'sent') {
    $stmt = $pdo->prepare(
        "SELECT m.*, r.full_name AS recipient_name, t.name AS team_name
         FROM messages m
         LEFT JOIN users r ON r.id = m.recipient_id
         LEFT JOIN teams t ON t.id = m.team_id
         WHERE m.sender_id = ? ORDER BY m.created_at DESC"
    );
    $stmt->execute([$user['id']]);
} else {
    $stmt = $pdo->prepare(
        "SELECT m.*, s.full_name AS sender_name, t.name AS team_name,
                EXISTS(SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?) AS is_read
         FROM messages m
         JOIN users s ON s.id = m.sender_id
         LEFT JOIN teams t ON t.id = m.team_id
         WHERE m.sender_id != ?
           AND (m.recipient_id = ? OR m.is_broadcast = 1 OR (m.team_id IS NOT NULL AND m.team_id = ?))
         ORDER BY m.created_at DESC"
    );
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['team_id']]);
}
$messages = $stmt->fetchAll();

$pageTitle = 'Messages';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Internal Messaging</h1>
    <p class="subtitle">Direct messages, team announcements and broadcasts.</p>
  </div>
  <a class="btn no-print" href="<?= base_path() ?>/message_compose.php">+ Compose</a>
</div>

<div class="card no-print">
  <a href="messages.php?box=inbox" class="btn-sm btn <?= $box === 'inbox' ? '' : 'btn-secondary' ?>">Inbox</a>
  <a href="messages.php?box=sent" class="btn-sm btn <?= $box === 'sent' ? '' : 'btn-secondary' ?>">Sent</a>
</div>

<div class="card">
  <div class="table-wrap">
  <?php if (!$messages): ?>
    <p class="empty-state">No messages here yet.</p>
  <?php else: ?>
  <table>
    <tr><th><?= $box === 'sent' ? 'To' : 'From' ?></th><th>Subject</th><th>Date</th><th></th></tr>
    <?php foreach ($messages as $m): ?>
      <?php
        $who = $box === 'sent'
            ? ($m['is_broadcast'] ? 'Everyone (broadcast)' : ($m['team_name'] ? 'Team: ' . $m['team_name'] : $m['recipient_name']))
            : ($m['sender_name'] . ($m['is_broadcast'] ? ' (broadcast)' : ($m['team_name'] ? ' (team: ' . $m['team_name'] . ')' : '')));
      ?>
      <tr style="<?= $box === 'inbox' && !$m['is_read'] ? 'font-weight:700;' : '' ?>">
        <td><?= e($who) ?></td>
        <td><?= e($m['subject'] ?: '(no subject)') ?></td>
        <td><?= format_datetime($m['created_at']) ?></td>
        <td><a class="btn btn-sm btn-secondary" href="<?= base_path() ?>/message_view.php?id=<?= (int) $m['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
