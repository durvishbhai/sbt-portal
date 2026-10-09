<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT m.*, s.full_name AS sender_name, r.full_name AS recipient_name, t.name AS team_name
     FROM messages m
     JOIN users s ON s.id = m.sender_id
     LEFT JOIN users r ON r.id = m.recipient_id
     LEFT JOIN teams t ON t.id = m.team_id
     WHERE m.id = ?"
);
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    http_response_code(404);
    die('Message not found.');
}

$isRecipient = $message['recipient_id'] == $user['id']
    || $message['is_broadcast']
    || ($message['team_id'] && $message['team_id'] == $user['team_id']);
$isSender = $message['sender_id'] == $user['id'];

if (!$isRecipient && !$isSender) {
    http_response_code(403);
    die('You do not have permission to view this message.');
}

if ($isRecipient && !$isSender) {
    $pdo->prepare('INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)')->execute([$id, $user['id']]);
}

$pageTitle = $message['subject'] ?: 'Message';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e($message['subject'] ?: '(no subject)') ?></h1>
    <p class="subtitle">
      From <?= e($message['sender_name']) ?>
      to <?= $message['is_broadcast'] ? 'Everyone' : ($message['team_name'] ? 'Team: ' . e($message['team_name']) : e($message['recipient_name'])) ?>
      &middot; <?= format_datetime($message['created_at']) ?>
    </p>
  </div>
</div>

<div class="card">
  <p style="white-space:pre-wrap;"><?= nl2br(e($message['body'])) ?></p>
</div>

<a class="btn btn-secondary no-print" href="<?= base_path() ?>/messages.php">&larr; Back to messages</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
