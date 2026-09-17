<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <img src="<?= base_path() ?>/assets/img/logo.svg" alt="Super Boys Team logo">
  <h1>SBT Portal</h1>
  <p>The secure digital platform for Super Boys Team — manage roles, competitions, courses, elections, complaints and internal services from one centralized dashboard.</p>
  <a class="btn" href="<?= base_path() ?>/register.php">Join SBT</a>
  <a class="btn btn-secondary" href="<?= base_path() ?>/login.php">Member Login</a>
</section>

<div class="grid grid-3">
  <div class="card">
    <h3>🔐 Secure &amp; Role-Based</h3>
    <p>Every member gets exactly the access their role needs — from Main Team Maker down to Janta — with full activity and security logging.</p>
  </div>
  <div class="card">
    <h3>🗳️ Elections &amp; Voting</h3>
    <p>Run transparent SBT elections with verified digital IDs, live candidate lists and published results.</p>
  </div>
  <div class="card">
    <h3>🪙 SBT Coins</h3>
    <p>Track member wallets, reward contributions, and keep a transparent transaction history for every account.</p>
  </div>
</div>

<div class="card">
  <h2>What the portal covers</h2>
  <div class="grid grid-2">
    <ul class="feature-list">
      <li>Secure login &amp; role-based access (10 roles)</li>
      <li>Team registration &amp; approval workflow</li>
      <li>Secure document vault &amp; verification</li>
      <li>Complaint &amp; reporting system</li>
      <li>Election &amp; voting system</li>
    </ul>
    <ul class="feature-list">
      <li>SBT Coins wallet &amp; transaction tracking</li>
      <li>Internal messaging &amp; team communication</li>
      <li>Admin control, activity &amp; security logs</li>
      <li>Emergency lockdown &amp; access control panel</li>
      <li>Mobile responsive dashboards &amp; reports</li>
    </ul>
  </div>
</div>

<div class="card">
  <h2>About Super Boys Team</h2>
  <p>Super Boys Team (SBT) is a community bringing together teams, members and departments under one coordinated structure. The SBT Portal centralizes operations, improves communication and gives every member — from Main Team Makers to Janta — a secure, structured way to participate.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
