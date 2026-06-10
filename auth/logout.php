<?php
require_once __DIR__ . '/../includes/auth.php';

// Empty the session array
$_SESSION = array();

// Destroy the session completely
session_destroy();

// Send user back to the login page
header("Location: /clearpath/auth/login.php");
exit();
