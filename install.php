<?php
/**
 * One-time setup wizard. Creates the first Main Team Maker (MTM) account.
 * Locks itself once a user already exists — delete this file after use if
 * you want to be extra safe, though it refuses to run again regardless.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$userCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount > 0) {
    http_response_code(403);
    $pageTitle = 'Setup already complete';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card">
      <h1>Setup already complete</h1>
      <p>An administrator account already exists. If you've lost access, ask another MTM/System Administrator to reset your password from the Admin &rarr; Users page.</p>
      <a class="btn" href="login.php">Go to login</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

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

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $accountNo = generate_sbt_account_no($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO users (sbt_account_no, full_name, email, phone, password_hash, role_id, status)
             VALUES (?, ?, ?, ?, ?, (SELECT id FROM roles WHERE slug = "mtm"), "active")'
        );
        $stmt->execute([$accountNo, $name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO wallet_accounts (user_id, balance) VALUES (?, 0)')->execute([$userId]);
        $pdo->commit();

        log_security($userId, 'login_success', 'Initial MTM account created via install wizard');
        log_activity($userId, 'account_created', 'Initial administrator account');

        flash('success', 'Administrator account created. You can now log in.');
        redirect('login.php');
    }
}

$pageTitle = 'Set up SBT Portal';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Welcome — let's set up your portal</h1>
    <p class="subtitle">Create the first Main Team Maker (MTM) account. This wizard only runs once.</p>
  </div>
</div>

<div class="card" style="max-width:520px;">
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label for="full_name">Full name</label>
    <input type="text" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

    <label for="phone">Phone / WhatsApp (optional)</label>
    <input type="tel" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">

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
    <p class="hint">Minimum 8 characters. This account gets full system control (MTM), so use a strong password.</p>
    <button type="submit" class="btn">Create administrator account</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
