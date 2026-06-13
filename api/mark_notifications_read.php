<?php
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
}
