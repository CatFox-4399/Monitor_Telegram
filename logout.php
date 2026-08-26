<?php
// logout.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logoutAdmin();
header('Location: ' . BASE_URL . '/login.php?msg=logged_out');
exit;
