<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role(ADMIN_ROLES);
$pdo = db();

$roles = $pdo->query('SELECT * FROM roles ORDER BY sort_order')->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams WHERE status = 'approved' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $formAction = $_POST['form_action'] ?? '';

    if ($targetId === (int) $user['id'] && in_array($formAction, ['suspend', 'delete'], true)) {
        flash('error', 'You cannot suspend or delete your own account.');
        redirect('users.php');
    }

    if ($formAction === 'activate') {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
        log_activity($user['id'], 'user_activated', 'User #' . $targetId);
        flash('success', 'User activated.');
    } elseif ($formAction === 'suspend') {
        $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$targetId]);
        log_security($targetId, 'account_suspended', 'By ' . $user['full_name']);
        log_activity($user['id'], 'user_suspended', 'User #' . $targetId);
        flash('success', 'User suspended.');
    } elseif ($formAction === 'change_role') {
        $newRoleId = (int) ($_POST['role_id'] ?? 0);
        $newTeamId = ($_POST['team_id'] ?? '') !== '' ? (int) $_POST['team_id'] : null;
        $pdo->prepare('UPDATE users SET role_id = ?, team_id = ? WHERE id = ?')->execute([$newRoleId, $newTeamId, $targetId]);
        log_security($targetId, 'role_changed', 'By ' . $user['full_name']);
        log_activity($user['id'], 'user_role_changed', 'User #' . $targetId);
        flash('success', 'Role updated.');
    } elseif ($formAction === 'reset_password' && in_array($user['role_slug'], ['mtm', 'sysadmin'], true)) {
        $newPassword = bin2hex(random_bytes(6));
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
        log_security($targetId, 'password_change', 'Reset by ' . $user['full_name']);
        log_activity($user['id'], 'user_password_reset', 'User #' . $targetId);
        flash('success', 'Temporary password: ' . $newPassword . ' — share this with the member securely and ask them to change it.');
    }
    redirect('users.php');
}

$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'active', 'suspended'];
$sql = "SELECT u.*, r.name AS role_name, t.name AS team_name FROM users u
        JOIN roles r ON r.id = u.role_id
        LEFT JOIN teams t ON t.id = u.team_id";
$params = [];
if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= ' WHERE u.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Manage Users</h1>
    <p class="subtitle">Activate accounts, manage roles, and handle access requests.</p>
  </div>
  <div class="no-print">
    <a class="btn btn-sm btn-secondary" href="logs.php">Activity Logs</a>
    <a class="btn btn-sm btn-secondary" href="settings.php">Settings</a>
  </div>
</div>

<div class="card no-print">
  <a href="users.php" class="btn-sm btn <?= $statusFilter === '' ? '' : 'btn-secondary' ?>">All</a>
  <a href="users.php?status=pending" class="btn-sm btn <?= $statusFilter === 'pending' ? '' : 'btn-secondary' ?>">Pending</a>
  <a href="users.php?status=active" class="btn-sm btn <?= $statusFilter === 'active' ? '' : 'btn-secondary' ?>">Active</a>
  <a href="users.php?status=suspended" class="btn-sm btn <?= $statusFilter === 'suspended' ? '' : 'btn-secondary' ?>">Suspended</a>
</div>

<div class="card">
  <div class="table-wrap">
  <?php if (!$users): ?>
    <p class="empty-state">No users found.</p>
  <?php else: ?>
  <table>
    <tr><th>Name</th><th>Account</th><th>Role</th><th>Team</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['full_name']) ?><br><span style="font-size:11px;color:#6b7280;"><?= e($u['email']) ?></span></td>
        <td><?= e($u['sbt_account_no']) ?></td>
        <td><?= e($u['role_name']) ?></td>
        <td><?= e($u['team_name'] ?? '-') ?></td>
        <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
        <td><?= format_datetime($u['created_at']) ?></td>
        <td>
          <?php if ($u['status'] === 'pending'): ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="form_action" value="activate">
              <button type="submit" class="btn btn-sm">Activate</button>
            </form>
          <?php elseif ($u['status'] === 'active' && (int) $u['id'] !== (int) $user['id']): ?>
            <form method="post" style="display:inline;" data-confirm="Suspend this account?">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="form_action" value="suspend">
              <button type="submit" class="btn btn-sm btn-danger">Suspend</button>
            </form>
          <?php elseif ($u['status'] === 'suspended'): ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="form_action" value="activate">
              <button type="submit" class="btn btn-sm">Reactivate</button>
            </form>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('roleForm<?= (int) $u['id'] ?>').style.display='block'">Edit role</button>

          <div id="roleForm<?= (int) $u['id'] ?>" style="display:none; margin-top:8px;">
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="form_action" value="change_role">
              <select name="role_id">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int) $r['id'] ?>" <?= (int) $u['role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="team_id">
                <option value="">No team</option>
                <?php foreach ($teams as $t): ?>
                  <option value="<?= (int) $t['id'] ?>" <?= (int) $u['team_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm">Save</button>
            </form>
            <?php if (in_array($user['role_slug'], ['mtm', 'sysadmin'], true)): ?>
            <form method="post" style="margin-top:6px;" data-confirm="Reset this user's password to a random temporary one?">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="form_action" value="reset_password">
              <button type="submit" class="btn btn-sm btn-secondary">Reset password</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
