<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

// Roles a visitor may self-select. Internal roles (MTM, TM, sysadmin,
// minister, deputy minister, auditor) are assigned by an administrator.
$selectableRoles = [
    'sbt_member' => 'SBT Member',
    'other_team_maker' => 'Other Team — Team Maker (register a new team)',
    'other_team_member' => 'Other Team — Team Member (join an existing approved team)',
    'janta' => 'Janta (general public)',
];

$approvedTeams = db()->query(
    "SELECT t.id, t.name FROM teams t WHERE t.status = 'approved' ORDER BY t.name"
)->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $roleSlug = $_POST['role_slug'] ?? '';
    $teamName = trim($_POST['team_name'] ?? '');
    $existingTeamId = $_POST['existing_team_id'] ?? '';

    if ($name === '' || $email === '') {
        $errors[] = 'Name and email are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!array_key_exists($roleSlug, $selectableRoles)) {
        $errors[] = 'Please choose how you want to join.';
    }
    if ($roleSlug === 'other_team_maker' && $teamName === '') {
        $errors[] = 'Please enter your team name.';
    }
    if ($roleSlug === 'other_team_member' && $existingTeamId === '') {
        $errors[] = 'Please select the team you want to join.';
    }

    if (!$errors) {
        $pdo = db();
        $emailExists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $emailExists->execute([$email]);
        if ((int) $emailExists->fetchColumn() > 0) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $teamId = null;
            if ($roleSlug === 'other_team_maker') {
                $stmt = $pdo->prepare(
                    "INSERT INTO teams (name, team_type, status) VALUES (?, 'other', 'pending')"
                );
                $stmt->execute([$teamName]);
                $teamId = (int) $pdo->lastInsertId();
            } elseif ($roleSlug === 'other_team_member') {
                $teamId = (int) $existingTeamId;
            }

            $accountNo = generate_sbt_account_no($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO users (sbt_account_no, full_name, email, phone, password_hash, role_id, team_id, status)
                 VALUES (?, ?, ?, ?, ?, (SELECT id FROM roles WHERE slug = ?), ?, "pending")'
            );
            $stmt->execute([$accountNo, $name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $roleSlug, $teamId]);
            $userId = (int) $pdo->lastInsertId();

            if ($roleSlug === 'other_team_maker') {
                $pdo->prepare('UPDATE teams SET created_by = ? WHERE id = ?')->execute([$userId, $teamId]);
            }

            $pdo->prepare('INSERT INTO wallet_accounts (user_id, balance) VALUES (?, 0)')->execute([$userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        log_activity($userId, 'account_registered', 'Role: ' . $roleSlug);
        flash('success', 'Account created! An administrator will review and activate your account before you can log in.');
        redirect('login.php');
    }
}

$pageTitle = 'Join SBT';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Join Super Boys Team</h1>
    <p class="subtitle">New accounts are reviewed by an administrator before they're activated.</p>
  </div>
</div>

<div class="card" style="max-width:560px;">
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label for="full_name">Full name</label>
    <input type="text" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>

    <div class="field-row">
      <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>
      <div>
        <label for="phone">Phone / WhatsApp</label>
        <input type="tel" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
      </div>
    </div>

    <div class="field-row">
      <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required>
      </div>
      <div>
        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
      </div>
    </div>

    <label for="role_slug">How would you like to join?</label>
    <select id="role_slug" name="role_slug" required onchange="document.getElementById('teamNameField').style.display = this.value==='other_team_maker' ? 'block':'none'; document.getElementById('existingTeamField').style.display = this.value==='other_team_member' ? 'block':'none';">
      <option value="">Select an option</option>
      <?php foreach ($selectableRoles as $slug => $label): ?>
        <option value="<?= e($slug) ?>" <?= ($_POST['role_slug'] ?? '') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <div id="teamNameField" style="display:none;">
      <label for="team_name">New team name</label>
      <input type="text" id="team_name" name="team_name" value="<?= e($_POST['team_name'] ?? '') ?>">
    </div>

    <div id="existingTeamField" style="display:none;">
      <label for="existing_team_id">Team to join</label>
      <select id="existing_team_id" name="existing_team_id">
        <option value="">Select a team</option>
        <?php foreach ($approvedTeams as $team): ?>
          <option value="<?= (int) $team['id'] ?>"><?= e($team['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$approvedTeams): ?><p class="hint">No approved teams yet — check back soon.</p><?php endif; ?>
    </div>

    <button type="submit" class="btn">Create account</button>
  </form>
  <p class="hint">Already have an account? <a href="<?= base_path() ?>/login.php">Log in</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
