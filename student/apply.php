<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = trim($_POST['course'] ?? '');
    $batch = trim($_POST['batch'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
    $emergency_justification = trim($_POST['emergency_justification'] ?? '');

    if ($course && $batch) {
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? AND overall_status != 'completed'");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $error = "You already have an active clearance application.";
        } else {
            // Handle file uploads
            $uploaded_files = [];
            if (isset($_FILES['documents']) && is_array($_FILES['documents']['name']) && $_FILES['documents']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $file_count = count($_FILES['documents']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) {
                        $error = "File upload error. Code: " . $_FILES['documents']['error'][$i];
                        break;
                    }
                    
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['documents']['type'][$i], $allowed_types)) {
                        $error = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
                        break;
                    } elseif ($_FILES['documents']['size'][$i] > $max_size) {
                        $error = "File is too large. Maximum size is 5MB.";
                        break;
                    } else {
                        $upload_dir = __DIR__ . '/../uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_ext = pathinfo($_FILES['documents']['name'][$i], PATHINFO_EXTENSION);
                        $new_filename = uniqid('doc_') . '.' . $file_ext;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $destination)) {
                            $uploaded_files[] = [
                                'name' => $_FILES['documents']['name'][$i],
                                'path' => 'uploads/' . $new_filename
                            ];
                        } else {
                            $error = "Failed to move uploaded file.";
                            break;
                        }
                    }
                }
            }

            if (!$error) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO applications (user_id, course, batch, reason, is_emergency, emergency_justification, overall_status) VALUES (?, ?, ?, ?, ?, ?, 'in_progress')");
                    $stmt->execute([$_SESSION['user_id'], $course, $batch, $reason, $is_emergency, $emergency_justification]);
                    $application_id = $pdo->lastInsertId();
                    
                    if (!empty($uploaded_files)) {
                        $stmt = $pdo->prepare("INSERT INTO documents (application_id, user_id, file_name, file_path) VALUES (?, ?, ?, ?)");
                        foreach ($uploaded_files as $file) {
                            $stmt->execute([$application_id, $_SESSION['user_id'], $file['name'], $file['path']]);
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET course = ?, batch = ? WHERE id = ?");
                    $stmt->execute([$course, $batch, $_SESSION['user_id']]);
                    
                    $stmt = $pdo->query("SELECT id FROM departments WHERE active = 1");
                    $departments = $stmt->fetchAll();
                    
                    $insert_dept = $pdo->prepare("INSERT INTO department_status (application_id, department_id, status) VALUES (?, ?, 'pending')");
                    foreach ($departments as $dept) {
                        $insert_dept->execute([$application_id, $dept['id']]);
                    }
                    
                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $student_name = $stmt->fetchColumn();

                    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'master_admin'");
                    $admins = $stmt->fetchAll();
                    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                    foreach ($admins as $admin) {
                        $notif_stmt->execute([$admin['id'], "New application from $student_name", '/clearpath/admin/student-details.php?id=' . $_SESSION['user_id']]);
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
            <?php if (!empty($user['course']) && !empty($user['batch'])): ?>
                <div style="background: var(--surface-2); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border);">
                    <div>
                        <div class="text-sm text-muted">Applying as</div>
                        <div class="font-medium"><?php echo htmlspecialchars($user['course'] . ' (' . $user['batch'] . ')'); ?></div>
                    </div>
                    <a href="/clearpath/profile.php" class="text-sm font-medium" style="color: var(--primary); text-decoration: none;">Change</a>
                </div>
                <input type="hidden" name="course" value="<?php echo htmlspecialchars($user['course']); ?>">
                <input type="hidden" name="batch" value="<?php echo htmlspecialchars($user['batch']); ?>">
            <?php else: ?>
                <div class="grid" style="grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group mb-0">
                        <label class="label" for="course">Course / Program</label>
                        <select class="select input" name="course" id="course" required>
                            <?php 
                            $selected_course = '';
                            require __DIR__ . '/../includes/course_options.php'; 
                            ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="label" for="batch">Batch / Trimester</label>
                        <input class="input" type="text" name="batch" id="batch" placeholder="241/242/243 etc." required>
                    </div>
                </div>
            <?php endif; ?>
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
                <label for="document" class="drop-zone" id="drop-zone" style="display: block; border: 2px dashed var(--border); border-radius: var(--radius-md); padding: 2.5rem 2rem; text-align: center; background-color: var(--surface-1); cursor: pointer; transition: all 0.2s;">
                    <i data-lucide="cloud-upload" style="width: 32px; height: 32px; color: var(--muted-foreground); margin: 0 auto 0.5rem auto; display: block;"></i>
                    <p class="text-sm font-medium" style="margin-bottom: 0.25rem;">Drag & drop or click to upload</p>
                    <p class="text-xs text-muted">PDF, JPG up to 5MB</p>
                    <input type="file" name="documents[]" id="document" multiple accept=".pdf, .jpg, .jpeg, .png" style="display: none;">
                </label>
                <div id="file-list" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

            <script>
                const fileInput = document.getElementById('document');
                const fileList = document.getElementById('file-list');
                const dropZone = document.getElementById('drop-zone');
                let selectedFiles = new DataTransfer();

                function renderFileList() {
                    fileList.innerHTML = '';
                    Array.from(selectedFiles.files).forEach((file, index) => {
                        const sizeKB = (file.size / 1024).toFixed(0);
                        
                        const fileCard = document.createElement('div');
                        fileCard.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface-1);';
                        
                        fileCard.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <i data-lucide="file-text" style="width: 16px; height: 16px; color: var(--muted-foreground);"></i>
                                <span class="text-sm" style="color: var(--foreground);">${file.name}</span>
                                <span class="text-sm text-muted">${sizeKB} KB</span>
                            </div>
                            <button type="button" onclick="removeFile(${index})" style="background: none; border: none; cursor: pointer; padding: 0.25rem; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="x" style="width: 16px; height: 16px; color: var(--muted-foreground);"></i>
                            </button>
                        `;
                        fileList.appendChild(fileCard);
                    });
                    lucide.createIcons();
                }

                function removeFile(index) {
                    const dt = new DataTransfer();
                    const files = Array.from(selectedFiles.files);
                    files.splice(index, 1);
                    files.forEach(file => dt.items.add(file));
                    selectedFiles = dt;
                    fileInput.files = selectedFiles.files;
                    renderFileList();
                }

                fileInput.addEventListener('change', (e) => {
                    Array.from(e.target.files).forEach(file => selectedFiles.items.add(file));
                    fileInput.files = selectedFiles.files;
                    renderFileList();
                });

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, unhighlight, false);
                });

                function highlight(e) {
                    dropZone.style.borderColor = 'var(--primary)';
                    dropZone.style.backgroundColor = 'color-mix(in srgb, var(--primary) 5%, var(--surface-1))';
                }

                function unhighlight(e) {
                    dropZone.style.borderColor = 'var(--border)';
                    dropZone.style.backgroundColor = 'var(--surface-1)';
                }

                dropZone.addEventListener('drop', (e) => {
                    let dt = e.dataTransfer;
                    let files = dt.files;
                    Array.from(files).forEach(file => selectedFiles.items.add(file));
                    fileInput.files = selectedFiles.files;
                    renderFileList();
                });
            </script>
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