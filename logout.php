<?php
require_once __DIR__ . '/includes/bootstrap.php';

do_logout();
session_start();
flash('info', 'You have been logged out.');
redirect('login.php');
