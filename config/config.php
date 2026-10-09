<?php
/**
 * SBT Portal - Core configuration.
 *
 * Real credentials do NOT belong in this file — it's committed to git.
 * Instead, either set the SBT_DB_* environment variables on your host
 * (cPanel: MultiPHP INI Editor / "Setup Node.js App" style env panels, or
 * an .env loaded by your hosting panel), or create config/config.local.php
 * (already git-ignored — see .gitignore) with content like:
 *
 *   <?php
 *   putenv('SBT_DB_NAME=your_db_name');
 *   putenv('SBT_DB_USER=your_db_user');
 *   putenv('SBT_DB_PASS=your_db_password');
 *   putenv('SBT_APP_URL=https://your-domain.example');
 *
 * That file is loaded automatically below if present.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// ---- Database ----
define('DB_HOST', getenv('SBT_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('SBT_DB_NAME') ?: 'sbt_portal');
define('DB_USER', getenv('SBT_DB_USER') ?: 'sbt_user');
define('DB_PASS', getenv('SBT_DB_PASS') ?: '');
define('DB_SOCKET', getenv('SBT_DB_SOCKET') ?: ''); // optional unix socket for local dev

// ---- App ----
define('APP_NAME', 'SBT Portal');
define('APP_URL', getenv('SBT_APP_URL') ?: '');
define('APP_TIMEZONE', 'Asia/Kolkata');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB

date_default_timezone_set(APP_TIMEZONE);

// ---- Error handling ----
// Flip APP_DEBUG to false in production.
define('APP_DEBUG', getenv('SBT_APP_DEBUG') === '1');
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ---- Sessions ----
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}
