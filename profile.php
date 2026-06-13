<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!is_logged_in()) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $student_id = trim($_POST['student_id'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $batch = trim($_POST['batch'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error = "Name and Email are required.";
    } else {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Email is already in use by another account.";
        } else {
            try {
                $pdo->beginTransaction();
                
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $hash, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $user_id]);
                }
                
                if (current_user_role() === 'student') {
                    $stmt = $pdo->prepare("UPDATE users SET student_id = ?, course = ?, batch = ? WHERE id = ?");
                    $stmt->execute([$student_id, $course, $batch, $user_id]);
                }
                
                $pdo->commit();
                $success = "Profile updated successfully.";
                $_SESSION['user_email'] = $email; // Update session email
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to update profile: " . $e->getMessage();
            }
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$active_tab = 'profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="card-title text-2xl">Profile</h1>
        <p class="text-muted">Manage your personal information and account settings.</p>
    </div>
</div>

<div class="card" style="max-width: 800px;">
    <div class="card-header">
        <h2 class="card-title">Personal Information</h2>
    </div>
    <div class="card-content">
        <?php if ($error): ?>
            <div style="background: var(--status-emergency-bg); color: var(--status-emergency); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background: var(--status-approved-bg); color: var(--status-approved); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="label" for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="input" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="label" for="password">New Password</label>
                <input type="password" id="password" name="password" class="input" placeholder="Leave blank to keep current password">
            </div>

            <?php if ($user['role'] === 'student'): ?>
                <div style="border-top: 1px solid var(--border); margin: 2rem 0; padding-top: 1.5rem;">
                    <h3 class="font-semibold mb-4 text-sm">Academic Details</h3>
                    
                    <div class="form-group">
                        <label class="label" for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" class="input" value="<?php echo htmlspecialchars($user['student_id'] ?? ''); ?>">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1rem;">
                        <div class="form-group">
                            <label class="label" for="course">Course / Program</label>
                            <select class="select input" name="course" id="course">
                                <?php 
                                $selected_course = $user['course'] ?? '';
                                require __DIR__ . '/includes/course_options.php'; 
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label" for="batch">Batch</label>
                            <input type="text" id="batch" name="batch" class="input" value="<?php echo htmlspecialchars($user['batch'] ?? ''); ?>" placeholder="e.g. 242">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
