<?php
/**
 * Include this single file at the top of every page. It wires up config,
 * the database connection and auth/helper functions in the right order.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
