<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

if (!isset($_GET['id'])) {
    die("Application ID missing.");
}
$app_id = (int)$_GET['id'];

// Verify application belongs to user and is completed
$stmt = $pdo->prepare("SELECT a.*, u.full_name, u.student_id, u.course, u.batch FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ? AND a.user_id = ?");
$stmt->execute([$app_id, $_SESSION['user_id']]);
$app = $stmt->fetch();

if (!$app || $app['overall_status'] !== 'completed') {
    die("Certificate not available or unauthorized.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearance Certificate - <?php echo htmlspecialchars($app['full_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: hsl(22 95% 53%);
        }
        body {
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .certificate-container {
            width: 800px;
            height: 600px;
            background-color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            position: relative;
            padding: 40px;
            box-sizing: border-box;
            border: 10px solid var(--primary);
            text-align: center;
        }
        .certificate-container::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid color-mix(in srgb, var(--primary) 30%, transparent);
            pointer-events: none;
        }
        .header {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 3rem;
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .sub-header {
            font-size: 1.1rem;
            color: #4b5563;
            letter-spacing: 5px;
            text-transform: uppercase;
            margin-bottom: 60px;
        }
        .content {
            font-size: 1.1rem;
            color: #374151;
            line-height: 1.6;
        }
        .name {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: #111827;
            font-weight: 600;
            margin: 20px 0;
            border-bottom: 1px solid #d1d5db;
            display: inline-block;
            padding-bottom: 5px;
            min-width: 400px;
        }
        .details {
            margin-top: 30px;
            display: flex;
            justify-content: space-around;
            text-align: left;
            font-size: 1rem;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .detail-value {
            font-weight: 600;
            color: #111827;
        }
        .signatures {
            margin-top: 80px;
            display: flex;
            justify-content: space-around;
        }
        .signature-block {
            text-align: center;
        }
        .signature-line {
            width: 200px;
            border-bottom: 1px solid #111827;
            margin-bottom: 10px;
        }
        .signature-title {
            color: #4b5563;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stamp {
            position: absolute;
            bottom: 60px;
            right: 60px;
            width: 120px;
            height: 120px;
            border: 4px solid color-mix(in srgb, var(--primary) 20%, transparent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            color: color-mix(in srgb, var(--primary) 20%, transparent);
            font-size: 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
            transform: rotate(-15deg);
        }

        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s;
        }
        .print-btn:hover {
            background-color: color-mix(in srgb, var(--primary) 80%, black);
        }

        @media print {
            body {
                background-color: white;
            }
            .certificate-container {
                box-shadow: none;
                border-color: #111827; /* Fallback for print */
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .certificate-container::before {
                border-color: #d1d5db;
            }
            .stamp {
                border-color: #d1d5db;
                color: #d1d5db;
            }
            .print-btn {
                display: none;
            }
            @page {
                size: landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="certificate-container">
        <div class="header">Clearance Certificate</div>
        <div class="sub-header">ClearPath Administration</div>

        <div class="content">
            This is to certify that
            <br>
            <div class="name"><?php echo htmlspecialchars($app['full_name']); ?></div>
            <br>
            has successfully completed all necessary clearance requirements <br>across all university departments.
        </div>

        <div class="details">
            <div>
                <div class="detail-item">
                    <div class="detail-label">Application ID</div>
                    <div class="detail-value">#<?php echo str_pad($app['id'], 5, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Course / Program</div>
                    <div class="detail-value"><?php echo htmlspecialchars($app['course'] ?: 'N/A'); ?></div>
                </div>
            </div>
            <div>
                <div class="detail-item">
                    <div class="detail-label">Batch</div>
                    <div class="detail-value"><?php echo htmlspecialchars($app['batch'] ?: 'N/A'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date of Issue</div>
                    <div class="detail-value"><?php echo date('F d, Y'); ?></div>
                </div>
            </div>
        </div>

        <div class="signatures">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-title">Master Administrator</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-title">Registrar</div>
            </div>
        </div>

        <div class="stamp">Approved</div>
    </div>

    <button class="print-btn" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Save as PDF
    </button>

</body>
</html>
