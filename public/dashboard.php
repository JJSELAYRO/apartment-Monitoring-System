<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// Redirect based on the user's role
if ($_SESSION['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
    exit();
} elseif ($_SESSION['role'] === 'tenant') {
    header("Location: ../tenant/dashboard.php");
    exit();
} else {
    // If role is unknown, log out or handle error
    header("Location: login.php");
    exit();
}
?>