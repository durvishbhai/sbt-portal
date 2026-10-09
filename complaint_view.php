<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();
$canReview = in_array($user['role_slug'], ['mtm', 'tm', 'sysadmin', 'minister', 'deputy_minister'], true);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT c.*, s.full_name AS submitted_by_name, a.full_name AS assigned_to_name
     FROM complaints c
     LEFT JOIN users s ON s.id = c.submitted_by
     LEFT JOIN users a ON a.id = c.assigned_to
     WHERE c.id = ?"
);
$stmt->execute([$id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    http_response_code(404);
    die('Complaint not found.');
}
if (!$canReview && $complaint['submitted_by'] != $user['id']) {
    http_response_code(403);
    die('You do not have permission to view this complaint.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_write_access();
    verify_csrf();
    if (!$canReview) {
        http_response_code(403);
        die('Only reviewers can update this complaint.');
    }
    $action = $_POST['form_action'] ?? '';
    if ($action === 'assign') {
        $assignTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;
        $pdo->prepare('UPDATE complaints SET assigned_to = ?, status = IF(status = "open", "in_review", status) WHERE id = ?')
            ->execute([$assignTo, $id]);
        log_activity($user['id'], 'complaint_assigned', 'Complaint #' . $id);
        flash('success', 'Complaint assigned.');
    } elseif ($action === 'resolve') {
        $note = trim($_POST['resolution_note'] ?? '');
        $decision = $_POST['decision'] === 'rejected' ? 'rejected' : 'resolved';
        $pdo->prepare('UPDATE complaints SET status = ?, resolution_note = ?, resolved_at = NOW() WHERE id = ?')
            ->execute([$decision, $note ?: null, $id]);
        log_activity($user['id'], 'complaint_' . $decision, 'Complaint #' . $id);
        flash('success', 'Complaint updated.');
    }
    redirect('complaint_view.php?id=' . $id);
}

$reviewers = [];
if ($canReview) {
    $reviewers = $pdo->query(
        "SELECT u.id, u.full_name, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.slug IN ('mtm','tm','sysadmin','minister','deputy_minister') AND u.status = 'active'
         ORDER BY u.full_name"
    )->fetchAll();
}

$pageTitle = 'Complaint #' . $id;
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e($complaint['subject']) ?></h1>
    <p class="subtitle">Submitted by <?= e($complaint['submitted_by_name'] ?? $complaint['submitter_name']) ?> on <?= format_datetime($complaint['created_at']) ?></p>
  </div>
  <span class="badge badge-<?= e($complaint['status']) ?>"><?= e(str_replace('_', ' ', ucfirst($complaint['status']))) ?></span>
</div>

<div class="card">
  <h3>Details</h3>
  <p><?= nl2br(e($complaint['description'])) ?></p>
  <?php if ($complaint['submitter_email']): ?><p class="hint">Contact: <?= e($complaint['submitter_email']) ?></p><?php endif; ?>
</div>

<?php if ($complaint['resolution_note']): ?>
<div class="card">
  <h3>Resolution</h3>
  <p><?= nl2br(e($complaint['resolution_note'])) ?></p>
  <p class="hint">Resolved <?= format_datetime($complaint['resolved_at']) ?></p>
</div>
<?php endif; ?>

<?php if ($canReview && in_array($complaint['status'], ['open', 'in_review'], true)): ?>
<div class="card no-print">
  <h3>Assign</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="form_action" value="assign">
    <select name="assigned_to">
      <option value="">Unassigned</option>
      <?php foreach ($reviewers as $r): ?>
        <option value="<?= (int) $r['id'] ?>" <?= (int) $complaint['assigned_to'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?> (<?= e($r['role_name']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm">Update assignment</button>
  </form>
</div>

<div class="card no-print">
  <h3>Resolve</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="form_action" value="resolve">
    <label for="resolution_note">Resolution note</label>
    <textarea id="resolution_note" name="resolution_note"></textarea>
    <label for="decision">Decision</label>
    <select id="decision" name="decision">
      <option value="resolved">Mark resolved</option>
      <option value="rejected">Reject / no action needed</option>
    </select>
    <button type="submit" class="btn">Save</button>
  </form>
</div>
<?php endif; ?>

<a class="btn btn-secondary no-print" href="<?= base_path() ?>/complaints.php">&larr; Back to complaints</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
