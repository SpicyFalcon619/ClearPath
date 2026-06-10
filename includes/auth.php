<?php
// includes/auth.php
// This file manages user sessions (login status) and security.

// Start the PHP session so we can store variables like $_SESSION['user_id']
session_start();

// We almost always need the database when checking auth, so we include it here
require_once __DIR__ . '/db.php';

// Function to check if ANY user is logged in
function is_logged_in() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Verify that this user ID actually exists in our current database
    // This prevents bugs if you had an old session from another localhost project (like UIU-Nest)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        // The user was in the session but not in the DB! Clear the session.
        $_SESSION = array();
        session_destroy();
        return false;
    }
    
    return true;
}

// Function to get the current user's role ('student', 'dept_admin', or 'master_admin')
function current_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Function to protect pages. If the user doesn't have the right role, kick them out!
function require_role($required_role) {
    // If they aren't logged in at all, send to login page
    if (!is_logged_in()) {
        header("Location: /clearpath/auth/login.php");
        exit(); // Stop running the rest of the page
    }
    
    // If they are logged in but have the WRONG role...
    if (current_user_role() !== $required_role) {
        $role = current_user_role();
        
        // Redirect them to their proper dashboard
        if ($role === 'student') {
            header("Location: /clearpath/student/dashboard.php");
        } elseif ($role === 'dept_admin') {
            header("Location: /clearpath/dept/dashboard.php");
        } elseif ($role === 'master_admin') {
            header("Location: /clearpath/admin/dashboard.php");
        } else {
            header("Location: /clearpath/auth/login.php");
        }
        exit();
    }
}
