<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_role(ADMIN_ROLES);
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $startsAt = $_POST['starts_at'] ?? '';
    $endsAt = $_POST['ends_at'] ?? '';

    if ($title === '' || !$startsAt || !$endsAt) {
        flash('error', 'Title, start and end date/time are required.');
    } elseif (strtotime($endsAt) <= strtotime($startsAt)) {
        flash('error', 'End time must be after the start time.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO elections (title, description, starts_at, ends_at, status, created_by) VALUES (?, ?, ?, ?, "draft", ?)'
        );
        $stmt->execute([$title, $description ?: null, $startsAt, $endsAt, $user['id']]);
        log_activity($user['id'], 'election_created', $title);
        flash('success', 'Election created as draft. Add candidates before activating it.');
    }
    redirect('elections.php');
}

$elections = $pdo->query(
    "SELECT e.*, u.full_name AS created_by_name,
            (SELECT COUNT(*) FROM election_candidates ec WHERE ec.election_id = e.id) AS candidate_count,
            (SELECT COUNT(*) FROM election_votes ev WHERE ev.election_id = e.id) AS vote_count
     FROM elections e LEFT JOIN users u ON u.id = e.created_by
     ORDER BY e.starts_at DESC"
)->fetchAll();

$pageTitle = 'Elections';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Elections &amp; Voting</h1>
    <p class="subtitle">Transparent SBT elections with verified voting and published results.</p>
  </div>
</div>

<?php if (is_admin($user)): ?>
<div class="card" style="max-width:560px;">
  <h3>Create a new election</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" required>
    <label for="description">Description</label>
    <textarea id="description" name="description"></textarea>
    <div class="field-row">
      <div>
        <label for="starts_at">Starts</label>
        <input type="datetime-local" id="starts_at" name="starts_at" required>
      </div>
      <div>
        <label for="ends_at">Ends</label>
        <input type="datetime-local" id="ends_at" name="ends_at" required>
      </div>
    </div>
    <button type="submit" class="btn">Create draft election</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
  <?php if (!$elections): ?>
    <p class="empty-state">No elections have been scheduled yet.</p>
  <?php else: ?>
  <table>
    <tr><th>Title</th><th>Status</th><th>Window</th><th>Candidates</th><th>Votes cast</th><th></th></tr>
    <?php foreach ($elections as $el): ?>
      <tr>
        <td><strong><?= e($el['title']) ?></strong></td>
        <td><span class="badge badge-<?= e($el['status']) ?>"><?= e(ucfirst($el['status'])) ?></span></td>
        <td style="font-size:12px;"><?= format_datetime($el['starts_at']) ?> &rarr; <?= format_datetime($el['ends_at']) ?></td>
        <td><?= (int) $el['candidate_count'] ?></td>
        <td><?= (int) $el['vote_count'] ?></td>
        <td><a class="btn btn-sm btn-secondary" href="<?= base_path() ?>/election.php?id=<?= (int) $el['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
