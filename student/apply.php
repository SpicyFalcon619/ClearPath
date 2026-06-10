<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = trim($_POST['course'] ?? '');
    $batch = trim($_POST['batch'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
    $emergency_justification = trim($_POST['emergency_justification'] ?? '');

    if ($course && $batch) {
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $error = "You already have an active clearance application.";
        } else {
            // Handle file upload check first
            $uploaded_file = null;
            if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                    $error = "File upload error. Code: " . $_FILES['document']['error'];
                } else {
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['document']['type'], $allowed_types)) {
                        $error = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
                    } elseif ($_FILES['document']['size'] > $max_size) {
                        $error = "File is too large. Maximum size is 5MB.";
                    } else {
                        $upload_dir = __DIR__ . '/../uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                        $new_filename = uniqid('doc_') . '.' . $file_ext;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
                            $uploaded_file = [
                                'name' => $_FILES['document']['name'],
                                'path' => 'uploads/' . $new_filename
                            ];
                        } else {
                            $error = "Failed to move uploaded file.";
                        }
                    }
                }
            }

            if (!$error) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO applications (user_id, reason, is_emergency, emergency_justification, overall_status) VALUES (?, ?, ?, ?, 'in_progress')");
                    $stmt->execute([$_SESSION['user_id'], $reason, $is_emergency, $emergency_justification]);
                    $application_id = $pdo->lastInsertId();
                    
                    if ($uploaded_file) {
                        $stmt = $pdo->prepare("INSERT INTO documents (application_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$application_id, $_SESSION['user_id'], $uploaded_file['name'], $uploaded_file['path']]);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET course = ?, batch = ? WHERE id = ?");
                    $stmt->execute([$course, $batch, $_SESSION['user_id']]);
                    
                    $stmt = $pdo->query("SELECT id FROM departments WHERE active = 1");
                    $departments = $stmt->fetchAll();
                    
                    $insert_dept = $pdo->prepare("INSERT INTO department_status (application_id, department_id, status) VALUES (?, ?, 'pending')");
                    foreach ($departments as $dept) {
                        $insert_dept->execute([$application_id, $dept['id']]);
                    }
                    
                    $pdo->commit();
                    header("Location: dashboard.php");
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Failed to submit application: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "Course and Batch are required.";
    }
}
?>

<?php 
$page_title = 'New Application';
$active_tab = 'apply';
require_once __DIR__ . '/../includes/header.php'; 
?>

<div class="mb-6">
    <h1 class="card-title text-2xl">New clearance application</h1>
    <p class="text-muted">Provide your details and upload supporting documents.</p>
</div>

<?php if ($error): ?>
    <div style="color: var(--destructive); background-color: var(--destructive-foreground); border: 1px solid var(--destructive); padding: 0.75rem; border-radius: var(--radius-md); font-size: 0.875rem; margin-bottom: 1rem;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form action="apply.php" method="POST" enctype="multipart/form-data" class="flex-col gap-6">
    <div class="card mb-6">
        <div class="card-header border-b border-border mb-4" style="padding-bottom: 1rem;">
            <h2 class="card-title text-lg">Course details</h2>
        </div>
        <div class="card-content">
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group mb-0">
                    <label class="label" for="course">Course / Program</label>
                    <select class="select input" name="course" id="course" required>
                        <option value="">Select your program...</option>
                        <optgroup label="School of Science and Engineering">
                            <option value="CSE">B.Sc. in Computer Science and Engineering (CSE)</option>
                            <option value="EEE">B.Sc. in Electrical and Electronic Engineering (EEE)</option>
                            <option value="CE">B.Sc. in Civil Engineering (CE)</option>
                            <option value="BSDS">B.Sc. in Data Science (BSDS)</option>
                        </optgroup>
                        <optgroup label="School of Business and Economics">
                            <option value="BBA">Bachelor of Business Administration (BBA)</option>
                            <option value="BBA in AIS">BBA in Accounting Information Systems (BBA in AIS)</option>
                            <option value="BSECO">Bachelor of Science in Economics (BSECO)</option>
                        </optgroup>
                        <optgroup label="School of Humanities and Social Sciences">
                            <option value="BA in English">Bachelor of Arts in English (BA in English)</option>
                            <option value="BSSMSJ">Bachelor of Social Science in Media Studies and Journalism (BSSMSJ)</option>
                            <option value="BSSEDS">Bachelor of Social Science in Environment and Development Studies (BSSEDS)</option>
                        </optgroup>
                        <optgroup label="School of Life Sciences">
                            <option value="B.Pharm">Bachelor of Pharmacy (B.Pharm)</option>
                            <option value="BSBGE">B.Sc. in Biotechnology & Genetic Engineering (BSBGE)</option>
                        </optgroup>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="label" for="batch">Batch / Trimester</label>
                    <input class="input" type="text" name="batch" id="batch" placeholder="241/242/243 etc." required>
                </div>
            </div>
            <div class="form-group mb-0">
                <label class="label" for="reason">Reason for clearance (optional)</label>
                <textarea class="textarea" name="reason" id="reason" placeholder="Graduation, semester drop, transfer, etc."></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header border-b border-border mb-4" style="padding-bottom: 1rem;">
            <h2 class="card-title text-lg">Supporting documents</h2>
            <p class="card-description">PDF, JPG, or PNG • max 5MB each</p>
        </div>
        <div class="card-content">
            <style>
                .drop-zone:hover {
                    border-color: var(--primary) !important;
                    background-color: color-mix(in srgb, var(--primary) 5%, var(--surface-1)) !important;
                }
            </style>
            <div class="form-group mb-0">
                <div class="drop-zone" style="border: 2px dashed var(--border); border-radius: var(--radius-md); padding: 2rem; text-align: center; background-color: var(--surface-1); cursor: pointer; transition: all 0.2s; position: relative;">
                    <i data-lucide="upload-cloud" style="width: 32px; height: 32px; color: var(--primary); margin: 0 auto 0.5rem auto; display: block;"></i>
                    <p class="text-sm font-medium" style="margin-bottom: 0.25rem;">Click to upload a document</p>
                    <p class="text-xs text-muted" id="file-name-display">No file chosen</p>
                    <input type="file" name="document" id="document" accept=".pdf, .jpg, .jpeg, .png" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;" onchange="document.getElementById('file-name-display').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-6" style="border-color: color-mix(in srgb, var(--status-emergency) 30%, var(--border));">
        <div class="card-header" style="flex-direction: row; align-items: center; justify-content: space-between; padding-bottom: 1rem;">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="alert-triangle" style="width: 20px; height: 20px; color: var(--status-emergency);"></i>
                    <h3 class="card-title text-lg" style="color: var(--status-emergency);">Emergency request</h3>
                </div>
                <p class="card-description">Mark this application as urgent for prioritized review.</p>
            </div>
            <label style="position: relative; display: inline-block; width: 44px; height: 24px;">
                <input type="checkbox" name="is_emergency" id="emergency-checkbox" value="1" style="opacity: 0; width: 0; height: 0;">
                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--input); border-radius: 34px; transition: .4s;"></span>
                <span id="slider-knob" style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: .4s; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"></span>
            </label>
        </div>
        <div class="card-content" id="emergency-justification-box" style="display: none; padding-top: 1rem; border-top: 1px solid var(--border);">
            <div class="form-group mb-0">
                <label class="label" for="emergency_justification">Emergency Justification</label>
                <textarea class="textarea" name="emergency_justification" id="emergency_justification" placeholder="Please provide a valid reason for the emergency request..." style="min-height: 100px;"></textarea>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-4">
        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Submit application</button>
    </div>
</form>

<script>
    const emergencyCheckbox = document.getElementById('emergency-checkbox');
    const emergencyBox = document.getElementById('emergency-justification-box');
    const sliderKnob = document.getElementById('slider-knob');
    
    emergencyCheckbox.addEventListener('change', function() {
        if (this.checked) {
            emergencyBox.style.display = 'block';
            emergencyBox.querySelector('textarea').required = true;
            this.nextElementSibling.style.backgroundColor = 'var(--status-emergency)';
            sliderKnob.style.transform = 'translateX(20px)';
        } else {
            emergencyBox.style.display = 'none';
            emergencyBox.querySelector('textarea').required = false;
            this.nextElementSibling.style.backgroundColor = 'var(--input)';
            sliderKnob.style.transform = 'translateX(0)';
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>