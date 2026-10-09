<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role(ADMIN_ROLES);
$pdo = db();

$tab = $_GET['tab'] ?? 'activity';

if ($tab === 'security') {
    $rows = $pdo->query(
        "SELECT s.*, u.full_name FROM security_logs s LEFT JOIN users u ON u.id = s.user_id
         ORDER BY s.id DESC LIMIT 300"
    )->fetchAll();
} else {
    $rows = $pdo->query(
        "SELECT a.*, u.full_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT 300"
    )->fetchAll();
}

if (($_GET['export'] ?? '') === 'csv') {
    $headers = $tab === 'security'
        ? ['Date', 'User', 'Event', 'Details', 'IP Address']
        : ['Date', 'User', 'Action', 'Details', 'IP Address'];
    $csvRows = array_map(fn ($r) => [
        $r['created_at'],
        $r['full_name'] ?? 'System',
        $r[$tab === 'security' ? 'event_type' : 'action'],
        $r['details'],
        $r['ip_address'],
    ], $rows);
    export_csv('sbt-' . $tab . '-logs.csv', $headers, $csvRows);
}

$pageTitle = 'Logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Activity &amp; Security Logs</h1>
    <p class="subtitle">Admin control &amp; security monitoring. Showing the latest 300 entries.</p>
  </div>
  <a class="btn btn-secondary btn-sm no-print" href="?tab=<?= e($tab) ?>&export=csv">Export CSV</a>
</div>

<div class="card no-print">
  <a href="logs.php?tab=activity" class="btn-sm btn <?= $tab === 'activity' ? '' : 'btn-secondary' ?>">Activity Logs</a>
  <a href="logs.php?tab=security" class="btn-sm btn <?= $tab === 'security' ? '' : 'btn-secondary' ?>">Security Logs</a>
</div>

<div class="card">
  <div class="table-wrap">
  <?php if (!$rows): ?>
    <p class="empty-state">No log entries yet.</p>
  <?php else: ?>
  <table>
    <tr><th>Date</th><th>User</th><th><?= $tab === 'security' ? 'Event' : 'Action' ?></th><th>Details</th><th>IP</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td style="white-space:nowrap;"><?= format_datetime($r['created_at']) ?></td>
        <td><?= e($r['full_name'] ?? 'System') ?></td>
        <td><?= e(str_replace('_', ' ', $r[$tab === 'security' ? 'event_type' : 'action'])) ?></td>
        <td><?= e($r['details'] ?? '') ?></td>
        <td><?= e($r['ip_address'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
