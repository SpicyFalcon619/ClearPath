<?php
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || current_user_role() !== 'student') {
    http_response_code(401);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ds_id = (int)($_POST['ds_id'] ?? 0);
    
    if ($ds_id) {
        $stmt = $pdo->prepare("UPDATE department_status SET unread_student_updates = 0 WHERE id = ?");
        $stmt->execute([$ds_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
}
