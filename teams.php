<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_write_access();
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $type = ($_POST['team_type'] ?? 'other') === 'sbt' ? 'sbt' : 'other';
    $description = trim($_POST['description'] ?? '');

    if ($type === 'sbt' && !is_admin($user)) {
        $type = 'other';
    }

    if ($name === '') {
        flash('error', 'Team name is required.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO teams (name, team_type, description, status, created_by) VALUES (?, ?, ?, "pending", ?)'
        );
        $stmt->execute([$name, $type, $description ?: null, $user['id']]);
        log_activity($user['id'], 'team_registered', $name);
        flash('success', 'Team registration submitted for approval.');
    }
    redirect('teams.php');
}

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'approved', 'rejected'];
$sql = "SELECT t.*, u.full_name AS created_by_name, a.full_name AS approved_by_name,
               (SELECT COUNT(*) FROM users WHERE team_id = t.id) AS member_count
        FROM teams t
        LEFT JOIN users u ON u.id = t.created_by
        LEFT JOIN users a ON a.id = t.approved_by";
$params = [];
if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= ' WHERE t.status = ?';
    $params[] = $statusFilter;
} elseif (!is_admin($user)) {
    $sql .= " WHERE t.status = 'approved' OR t.created_by = ?";
    $params[] = $user['id'];
}
$sql .= ' ORDER BY t.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teams = $stmt->fetchAll();

$pageTitle = 'Teams';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Teams</h1>
    <p class="subtitle">Team registration &amp; approval workflow.</p>
  </div>
  <?php if (!is_view_only($user)): ?>
  <button class="btn no-print" onclick="document.getElementById('newTeamForm').style.display='block'">+ Register a team</button>
  <?php endif; ?>
</div>

<?php if (!is_view_only($user)): ?>
<div class="card" id="newTeamForm" style="display:none; max-width:520px;">
  <h3>Register a new team</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label for="name">Team name</label>
    <input type="text" id="name" name="name" required>
    <?php if (is_admin($user)): ?>
    <label for="team_type">Team type</label>
    <select id="team_type" name="team_type">
      <option value="other">Other Team</option>
      <option value="sbt">SBT Team</option>
    </select>
    <?php endif; ?>
    <label for="description">Description (optional)</label>
    <textarea id="description" name="description"></textarea>
    <button type="submit" class="btn">Submit for approval</button>
  </form>
</div>
<?php endif; ?>

<div class="card no-print">
  <a href="teams.php" class="btn-sm btn <?= $statusFilter === '' ? '' : 'btn-secondary' ?>">All</a>
  <a href="teams.php?status=pending" class="btn-sm btn <?= $statusFilter === 'pending' ? '' : 'btn-secondary' ?>">Pending</a>
  <a href="teams.php?status=approved" class="btn-sm btn <?= $statusFilter === 'approved' ? '' : 'btn-secondary' ?>">Approved</a>
  <a href="teams.php?status=rejected" class="btn-sm btn <?= $statusFilter === 'rejected' ? '' : 'btn-secondary' ?>">Rejected</a>
</div>

<div class="card">
  <div class="table-wrap">
  <?php if (!$teams): ?>
    <p class="empty-state">No teams found.</p>
  <?php else: ?>
  <table>
    <tr><th>Name</th><th>Type</th><th>Members</th><th>Status</th><th>Requested by</th><th>Created</th><?php if (is_admin($user)): ?><th>Actions</th><?php endif; ?></tr>
    <?php foreach ($teams as $team): ?>
      <tr>
        <td><strong><?= e($team['name']) ?></strong><?php if ($team['description']): ?><br><span style="color:#6b7280;font-size:12px;"><?= e($team['description']) ?></span><?php endif; ?></td>
        <td><?= $team['team_type'] === 'sbt' ? 'SBT Team' : 'Other Team' ?></td>
        <td><?= (int) $team['member_count'] ?> / <?= (int) $team['max_users'] ?></td>
        <td><span class="badge badge-<?= e($team['status']) ?>"><?= e(ucfirst($team['status'])) ?></span></td>
        <td><?= e($team['created_by_name'] ?? '-') ?></td>
        <td><?= format_datetime($team['created_at']) ?></td>
        <?php if (is_admin($user)): ?>
        <td>
          <?php if ($team['status'] === 'pending'): ?>
            <form method="post" action="team_approve.php" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
              <input type="hidden" name="decision" value="approve">
              <button type="submit" class="btn btn-sm">Approve</button>
            </form>
            <form method="post" action="team_approve.php" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
              <input type="hidden" name="decision" value="reject">
              <button type="submit" class="btn btn-sm btn-danger">Reject</button>
            </form>
          <?php else: ?>
            <span style="color:#6b7280;font-size:12px;"><?= $team['approved_by_name'] ? 'by ' . e($team['approved_by_name']) : '' ?></span>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
