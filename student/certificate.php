<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fpdf/fpdf.php';

if (!is_logged_in()) {
    die("Unauthorized.");
}

if (!isset($_GET['id'])) {
    die("Application ID missing.");
}
$app_id = (int)$_GET['id'];

$is_admin = current_user_role() === 'master_admin';

// Verify application belongs to user (or user is admin) and is completed
if ($is_admin) {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name, u.student_id, u.course as u_course, u.batch as u_batch FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$app_id]);
} else {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name, u.student_id, u.course as u_course, u.batch as u_batch FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ? AND a.user_id = ?");
    $stmt->execute([$app_id, $_SESSION['user_id']]);
}
$app = $stmt->fetch();
$course = $app['course'] ?? $app['u_course'];
$batch = $app['batch'] ?? $app['u_batch'];

if (!$app || $app['overall_status'] !== 'completed') {
    die("Certificate not available or unauthorized.");
}

// Check serial number and assign if needed
if ($app['serial_number'] === null) {
    $stmt = $pdo->query("SELECT MAX(serial_number) as max_sn FROM applications");
    $max = $stmt->fetchColumn();
    $new_sn = $max ? $max + 1 : 1001;
    $stmt = $pdo->prepare("UPDATE applications SET serial_number = ? WHERE id = ?");
    $stmt->execute([$new_sn, $app_id]);
    $app['serial_number'] = $new_sn;
}

// Fetch approved departments
$stmt = $pdo->prepare("SELECT d.name FROM department_status ds JOIN departments d ON ds.department_id = d.id WHERE ds.application_id = ? AND ds.status = 'approved' ORDER BY d.name ASC");
$stmt->execute([$app_id]);
$depts = $stmt->fetchAll(PDO::FETCH_COLUMN);
$depts_str = implode('  ' . chr(149) . '  ', $depts);

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// Colors
$primary_r = 230; $primary_g = 80; $primary_b = 20;

// Outer Border
$pdf->SetDrawColor($primary_r, $primary_g, $primary_b);
$pdf->SetLineWidth(3);
$pdf->Rect(10, 10, 277, 190);

// Inner Border
$pdf->SetLineWidth(0.5);
$pdf->Rect(14, 14, 269, 182);

// Title
$pdf->SetY(40);
$pdf->SetFont('Arial', 'B', 32);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 15, 'Certificate of Clearance', 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 8, 'UNIVERSITY ONLINE CLEARANCE SYSTEM', 0, 1, 'C');

$pdf->Ln(20);

// Certification Text
$pdf->SetFont('Arial', '', 16);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');

// Student Name
$pdf->SetFont('Arial', 'B', 28);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 20, $app['full_name'], 0, 1, 'C');

// Context
$pdf->SetFont('Arial', '', 14);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 8, 'enrolled in ' . $course . ' (' . $batch . ') has successfully completed all institutional clearance', 0, 1, 'C');
$pdf->Cell(0, 8, 'requirements across the following departments:', 0, 1, 'C');

$pdf->Ln(15);

// Departments List
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor($primary_r, $primary_g, $primary_b);
$pdf->Cell(0, 10, $depts_str, 0, 1, 'C');

// Footer Details
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(100, 100, 100);

$pdf->SetY(-45);
$pdf->SetX(25);
$pdf->Cell(100, 6, 'Serial No: SN-' . str_pad($app['serial_number'], 5, '0', STR_PAD_LEFT), 0, 1, 'L');

$pdf->SetX(25);
$pdf->Cell(100, 6, 'Reference: CL-' . date('Y', strtotime($app['created_at'])) . '-' . strtoupper(substr(md5($app['id']), 0, 8)), 0, 1, 'L');

$pdf->SetX(25);
$pdf->Cell(100, 6, 'Issued: ' . date('M d, Y'), 0, 1, 'L');

// Signature Line
$pdf->SetY(-35);
$pdf->SetX(-85);
$pdf->SetDrawColor(150, 150, 150);
$pdf->Cell(60, 6, "Registrar's Office", 'T', 0, 'C');

$pdf->Output('D', 'Clearance_Certificate.pdf');
