<?php
/**
 * Shared page header/nav. Expects $pageTitle to be set by the including
 * page. current_user() may be null on public pages.
 */
$authedUser = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= base_path() ?>/assets/img/logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= base_path() ?>/assets/css/style.css">
</head>
<body class="<?= $authedUser ? 'is-authed' : 'is-guest' ?>">
<?php if (is_lockdown_active()): ?>
<div class="lockdown-banner">⚠ Emergency lockdown is active — only administrators can make changes right now.</div>
<?php endif; ?>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="<?= base_path() ?>/<?= $authedUser ? 'dashboard.php' : 'index.php' ?>">
      <img src="<?= base_path() ?>/assets/img/logo.svg" alt="Super Boys Team logo" class="brand-logo">
      <span class="brand-text">SBT<small>Super Boys Team Portal</small></span>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <nav class="main-nav" id="mainNav">
      <?php if ($authedUser): ?>
        <a href="<?= base_path() ?>/dashboard.php">Dashboard</a>
        <a href="<?= base_path() ?>/teams.php">Teams</a>
        <a href="<?= base_path() ?>/documents.php">Documents</a>
        <a href="<?= base_path() ?>/complaints.php">Complaints</a>
        <a href="<?= base_path() ?>/elections.php">Elections</a>
        <a href="<?= base_path() ?>/wallet.php">SBT Coins</a>
        <a href="<?= base_path() ?>/messages.php">Messages</a>
        <?php if (is_admin($authedUser)): ?>
          <a href="<?= base_path() ?>/admin/users.php">Admin</a>
        <?php endif; ?>
        <span class="nav-user">
          <?= e($authedUser['full_name']) ?>
          <small><?= e($authedUser['role_name']) ?></small>
        </span>
        <a href="<?= base_path() ?>/logout.php" class="nav-logout">Logout</a>
      <?php else: ?>
        <a href="<?= base_path() ?>/index.php">Home</a>
        <a href="<?= base_path() ?>/login.php">Login</a>
        <a href="<?= base_path() ?>/register.php" class="btn-nav">Join SBT</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="site-main">
<?php foreach (get_flashes() as $flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
