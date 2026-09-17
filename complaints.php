<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();

$canReview = in_array($user['role_slug'], ['mtm', 'tm', 'sysadmin', 'minister', 'deputy_minister'], true);

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['open', 'in_review', 'resolved', 'rejected'];

$sql = "SELECT c.*, s.full_name AS submitted_by_name, a.full_name AS assigned_to_name
        FROM complaints c
        LEFT JOIN users s ON s.id = c.submitted_by
        LEFT JOIN users a ON a.id = c.assigned_to";
$params = [];
$conditions = [];
if (!$canReview) {
    $conditions[] = 'c.submitted_by = ?';
    $params[] = $user['id'];
}
if (in_array($statusFilter, $allowedStatuses, true)) {
    $conditions[] = 'c.status = ?';
    $params[] = $statusFilter;
}
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY c.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$pageTitle = 'Complaints';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Complaint &amp; Reporting System</h1>
    <p class="subtitle"><?= $canReview ? 'Review and resolve reports submitted by members and the public.' : 'Reports you have submitted.' ?></p>
  </div>
  <a class="btn no-print" href="<?= base_path() ?>/complaint.php">+ New complaint</a>
</div>

<div class="card no-print">
  <a href="complaints.php" class="btn-sm btn <?= $statusFilter === '' ? '' : 'btn-secondary' ?>">All</a>
  <a href="complaints.php?status=open" class="btn-sm btn <?= $statusFilter === 'open' ? '' : 'btn-secondary' ?>">Open</a>
  <a href="complaints.php?status=in_review" class="btn-sm btn <?= $statusFilter === 'in_review' ? '' : 'btn-secondary' ?>">In Review</a>
  <a href="complaints.php?status=resolved" class="btn-sm btn <?= $statusFilter === 'resolved' ? '' : 'btn-secondary' ?>">Resolved</a>
  <a href="complaints.php?status=rejected" class="btn-sm btn <?= $statusFilter === 'rejected' ? '' : 'btn-secondary' ?>">Rejected</a>
</div>

<div class="card">
  <div class="table-wrap">
  <?php if (!$complaints): ?>
    <p class="empty-state">No complaints found.</p>
  <?php else: ?>
  <table>
    <tr><th>Subject</th><th>Submitted by</th><th>Status</th><th>Assigned to</th><th>Date</th><th></th></tr>
    <?php foreach ($complaints as $c): ?>
      <tr>
        <td><?= e($c['subject']) ?></td>
        <td><?= e($c['submitted_by_name'] ?? $c['submitter_name']) ?></td>
        <td><span class="badge badge-<?= e($c['status']) ?>"><?= e(str_replace('_', ' ', ucfirst($c['status']))) ?></span></td>
        <td><?= e($c['assigned_to_name'] ?? '-') ?></td>
        <td><?= format_datetime($c['created_at']) ?></td>
        <td><a class="btn btn-sm btn-secondary" href="<?= base_path() ?>/complaint_view.php?id=<?= (int) $c['id'] ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
