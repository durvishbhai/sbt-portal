<?php
/**
 * Public complaint submission — no login required, so Janta / the general
 * public can report an issue directly.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['submitter_name'] ?? '');
    $email = trim($_POST['submitter_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $subject === '' || $description === '') {
        $errors[] = 'Name, subject and description are required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address, or leave it blank.';
    }
    if (is_lockdown_active()) {
        $errors[] = 'The portal is currently in emergency lockdown. Please try again later.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO complaints (submitted_by, submitter_name, submitter_email, subject, description, status)
             VALUES (?, ?, ?, ?, ?, "open")'
        );
        $stmt->execute([$user['id'] ?? null, $name, $email ?: null, $subject, $description]);
        log_activity($user['id'] ?? null, 'complaint_submitted', $subject);
        $submitted = true;
    }
}

$pageTitle = 'Submit a Complaint';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Submit a Complaint or Report</h1>
    <p class="subtitle">Open to SBT members and the general public.</p>
  </div>
</div>

<div class="card" style="max-width:560px;">
  <?php if ($submitted): ?>
    <div class="alert alert-success">Thank you — your report has been submitted and will be reviewed by the team.</div>
    <a class="btn btn-secondary" href="<?= base_path() ?>/<?= $user ? 'complaints.php' : 'index.php' ?>">Done</a>
  <?php else: ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="submitter_name">Your name</label>
      <input type="text" id="submitter_name" name="submitter_name" value="<?= e($_POST['submitter_name'] ?? ($user['full_name'] ?? '')) ?>" required>

      <label for="submitter_email">Email (optional)</label>
      <input type="email" id="submitter_email" name="submitter_email" value="<?= e($_POST['submitter_email'] ?? ($user['email'] ?? '')) ?>">

      <label for="subject">Subject</label>
      <input type="text" id="subject" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" required>

      <label for="description">Details</label>
      <textarea id="description" name="description" required><?= e($_POST['description'] ?? '') ?></textarea>

      <button type="submit" class="btn">Submit report</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
