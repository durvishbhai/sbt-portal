<?php
/**
 * SBT Portal - Core configuration.
 * Copy this file's values from config.sample.php on your hosting account
 * and fill in real database credentials before deploying.
 */

// ---- Database ----
define('DB_HOST', getenv('SBT_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('SBT_DB_NAME') ?: 'u781322779_sbt');
define('DB_USER', getenv('SBT_DB_USER') ?: 'u781322779_sbt');
define('DB_PASS', getenv('SBT_DB_PASS') ?: 'Sbt@12345@');
define('DB_SOCKET', getenv('SBT_DB_SOCKET') ?: ''); // optional unix socket for local dev

// ---- App ----
define('APP_NAME', 'SBT Portal');
define('APP_URL', getenv('sbt.durvishjawale.tech') ?: '');
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
