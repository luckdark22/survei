<?php
require_once '../includes/db.php';
require_once '../includes/utils.php';
session_start();

if (isset($_SESSION['admin_logged_in'])) {
    logActivity($pdo, 'LOGOUT', 'Admin logged out');
}

session_unset();
session_destroy();
header("Location: login");
exit;
?>
