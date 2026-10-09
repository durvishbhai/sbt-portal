<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $user = attempt_login($email, $password);
    if ($user) {
        redirect('dashboard.php');
    }

    // Distinguish "pending approval" without leaking whether the email exists otherwise.
    $stmt = db()->prepare('SELECT status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $status = $stmt->fetchColumn();
    if ($status === 'pending') {
        $error = 'Your account is awaiting administrator approval.';
    } elseif ($status === 'suspended') {
        $error = 'Your account has been suspended. Contact an administrator.';
    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Member Login</h1>
  </div>
</div>

<div class="card" style="max-width:420px;">
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn">Log in</button>
  </form>
  <p class="hint">New to SBT? <a href="<?= base_path() ?>/register.php">Create an account</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
