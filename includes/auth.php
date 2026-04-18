<?php
// includes/auth.php
session_start();

function checkAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login");
        exit;
    }
}
?>
