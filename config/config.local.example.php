<?php
/**
 * Template for local/production secrets.
 *
 * Copy this file to config/config.local.php (already git-ignored — see
 * .gitignore) and fill in your real values there. Never put real
 * credentials in config.php or any other tracked file.
 */

putenv('SBT_DB_HOST=localhost');
putenv('SBT_DB_NAME=your_db_name');
putenv('SBT_DB_USER=your_db_user');
putenv('SBT_DB_PASS=your_db_password');
putenv('SBT_APP_URL=https://your-domain.example');

// Optional: set to '1' to show PHP errors while debugging locally.
// Always leave this unset (or '0') in production.
// putenv('SBT_APP_DEBUG=1');
