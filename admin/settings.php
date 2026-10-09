<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role(ADMIN_ROLES);
$pdo = db();

$canLockdown = in_array($user['role_slug'], ['mtm', 'sysadmin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'lockdown') {
        if (!$canLockdown) {
            http_response_code(403);
            die('Only the Main Team Maker or System Administrator can control emergency lockdown.');
        }
        $enable = ($_POST['lockdown'] ?? '0') === '1';
        $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'lockdown_mode'")
            ->execute([$enable ? '1' : '0']);
        log_security($user['id'], $enable ? 'lockdown_enabled' : 'lockdown_disabled', 'By ' . $user['full_name']);
        log_activity($user['id'], $enable ? 'lockdown_enabled' : 'lockdown_disabled', null);
        flash('success', $enable ? 'Emergency lockdown enabled.' : 'Emergency lockdown disabled.');
    } elseif ($formAction === 'site_name') {
        $siteName = trim($_POST['site_name'] ?? '') ?: 'SBT Portal';
        $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'site_name'")
            ->execute([$siteName]);
        log_activity($user['id'], 'site_settings_updated', 'Site name: ' . $siteName);
        flash('success', 'Settings saved.');
    } elseif ($formAction === 'backup') {
        $pdo->prepare("UPDATE system_settings SET setting_value = NOW() WHERE setting_key = 'last_backup_at'")->execute();
        log_activity($user['id'], 'backup_marked', 'Manual backup recorded');
        flash('success', 'Backup timestamp recorded. Remember to actually export your database via your hosting control panel.');
    }
    redirect('settings.php');
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Admin Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Admin Settings</h1><p class="subtitle">Access permission control panel.</p></div></div>

<div class="card">
  <h3>System alerts &amp; emergency lockdown</h3>
  <p>When enabled, only administrators (MTM / TM / System Administrator) can create, edit or approve records. Everyone else gets read-only access.</p>
  <p>Status: <span class="badge badge-<?= $settings['lockdown_mode'] === '1' ? 'rejected' : 'approved' ?>"><?= $settings['lockdown_mode'] === '1' ? 'LOCKDOWN ACTIVE' : 'Normal operation' ?></span></p>
  <?php if ($canLockdown): ?>
  <form method="post" data-confirm="<?= $settings['lockdown_mode'] === '1' ? 'Disable emergency lockdown?' : 'Enable emergency lockdown? This restricts all non-admin users to read-only access.' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="form_action" value="lockdown">
    <input type="hidden" name="lockdown" value="<?= $settings['lockdown_mode'] === '1' ? '0' : '1' ?>">
    <button type="submit" class="btn <?= $settings['lockdown_mode'] === '1' ? '' : 'btn-danger' ?>">
      <?= $settings['lockdown_mode'] === '1' ? 'Disable lockdown' : 'Enable emergency lockdown' ?>
    </button>
  </form>
  <?php else: ?>
    <p class="hint">Only the Main Team Maker or System Administrator can toggle lockdown.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Site settings</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="form_action" value="site_name">
    <label for="site_name">Site name</label>
    <input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name'] ?? APP_NAME) ?>">
    <button type="submit" class="btn">Save</button>
  </form>
</div>

<div class="card">
  <h3>Backup &amp; recovery</h3>
  <p>Last recorded backup: <?= format_datetime($settings['last_backup_at'] ?? null) ?></p>
  <p class="hint">This portal does not run scheduled backups itself — use your hosting provider's database backup/export tool (e.g. phpMyAdmin export, or a cron job with <code>mysqldump</code>) and click below to log when you did.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="form_action" value="backup">
    <button type="submit" class="btn btn-secondary">Mark backup taken now</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
