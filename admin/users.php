<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('master_admin');

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_role') {
        $target_user_id = (int)$_POST['user_id'];
        $new_role = $_POST['role'];
        $dept_id = $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : null;
        
        if ($target_user_id === $_SESSION['user_id'] && $new_role !== 'master_admin') {
            $error_msg = "You cannot remove your own master admin privileges.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, department_id = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $dept_id, $target_user_id])) {
                $success_msg = "User role updated successfully.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $target_user_id = (int)$_POST['user_id'];
        if ($target_user_id === $_SESSION['user_id']) {
            $error_msg = "You cannot delete yourself.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target_user_id]);
                $success_msg = "User deleted successfully.";
            } catch (PDOException $e) {
                $error_msg = "Cannot delete user. They have associated records.";
            }
        }
    }
}

// Fetch all active departments for the role edit form
$stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$all_departments = $stmt->fetchAll();

// Search users
$search = trim($_GET['search'] ?? '');
$query = "
    SELECT u.id, u.full_name, u.email, u.role, d.name as dept_name, u.department_id 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    WHERE 1=1
";
$params = [];
if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY u.role ASC, u.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Users';
$active_tab = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="card-title text-2xl">Users</h1>
        <p class="text-muted">All accounts — students and administrators. Search and assign roles.</p>
    </div>
    <form action="users.php" method="GET" style="position: relative; width: 300px;">
        <i data-lucide="search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted-foreground);"></i>
        <input type="text" name="search" class="input" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 2.25rem;">
        <?php if ($search): ?>
            <a href="users.php" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--muted-foreground);"><i data-lucide="x" style="width: 14px; height: 14px;"></i></a>
        <?php endif; ?>
    </form>
</div>

<?php if ($error_msg): ?>
    <div style="color: var(--destructive); background-color: var(--destructive-foreground); border: 1px solid var(--destructive); padding: 0.75rem; border-radius: var(--radius-md); font-size: 0.875rem; text-align: center; margin-bottom: 1rem;">
        <?php echo htmlspecialchars($error_msg); ?>
    </div>
<?php endif; ?>
<?php if ($success_msg): ?>
    <div style="color: var(--status-approved); background-color: var(--status-approved-bg); border: 1px solid var(--status-approved); padding: 0.75rem; border-radius: var(--radius-md); font-size: 0.875rem; text-align: center; margin-bottom: 1rem;">
        <?php echo htmlspecialchars($success_msg); ?>
    </div>
<?php endif; ?>

<div class="flex-col gap-4">
    <?php foreach ($users as $u): ?>
        <div class="card" style="padding: 1.25rem;">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-lg flex items-center gap-2 mb-1">
                        <?php echo htmlspecialchars($u['full_name']); ?>
                        <?php if ($u['id'] === $_SESSION['user_id']): ?>
                            <span class="status-badge status-pending" style="font-size: 0.65rem; padding: 0.125rem 0.375rem;">You</span>
                        <?php endif; ?>
                    </h3>
                    <p class="text-sm text-muted"><?php echo htmlspecialchars($u['email']); ?></p>
                </div>
                
                <div class="flex items-center gap-4">
                    <?php if ($u['role'] === 'master_admin'): ?>
                        <span class="status-badge status-emergency" style="background: color-mix(in srgb, var(--primary) 15%, transparent); color: var(--primary);">Master Admin</span>
                    <?php elseif ($u['role'] === 'dept_admin'): ?>
                        <span class="status-badge" style="background: var(--surface-2); color: var(--foreground); border: 1px solid var(--border);">Dept Admin · <?php echo htmlspecialchars($u['dept_name']); ?></span>
                    <?php else: ?>
                        <span class="status-badge" style="background: transparent; color: var(--muted-foreground); border: 1px solid var(--border);">Student</span>
                    <?php endif; ?>
                    
                    <button class="btn btn-ghost" style="color: var(--primary); padding: 0.25rem 0.5rem; height: auto;" onclick="document.getElementById('edit-user-<?php echo $u['id']; ?>').style.display = document.getElementById('edit-user-<?php echo $u['id']; ?>').style.display === 'none' ? 'block' : 'none';">
                        Edit role
                    </button>
                    
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-ghost" style="color: var(--destructive); padding: 0.25rem 0.5rem; height: auto;">
                                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Form Dropdown -->
            <div id="edit-user-<?php echo $u['id']; ?>" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border);">
                <form action="users.php" method="POST" class="flex items-end gap-4">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                    
                    <div class="form-group mb-0" style="flex: 1;">
                        <label class="label text-sm">Role</label>
                        <select name="role" class="select role-select" data-userid="<?php echo $u['id']; ?>" onchange="toggleDeptSelect(this)">
                            <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="dept_admin" <?php echo $u['role'] === 'dept_admin' ? 'selected' : ''; ?>>Department Admin</option>
                            <option value="master_admin" <?php echo $u['role'] === 'master_admin' ? 'selected' : ''; ?>>Master Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-0 dept-container-<?php echo $u['id']; ?>" style="flex: 1; display: <?php echo $u['role'] === 'dept_admin' ? 'block' : 'none'; ?>;">
                        <label class="label text-sm">Assigned Department</label>
                        <select name="department_id" class="select dept-select-<?php echo $u['id']; ?>" <?php echo $u['role'] === 'dept_admin' ? 'required' : ''; ?>>
                            <option value="">Select a department...</option>
                            <?php foreach ($all_departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $u['department_id'] == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="height: 2.5rem;">Save changes</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (count($users) === 0): ?>
        <div class="card text-center py-8 text-muted">No users found.</div>
    <?php endif; ?>
</div>

<script>
    function toggleDeptSelect(selectElement) {
        const userId = selectElement.getAttribute('data-userid');
        const deptContainer = document.querySelector('.dept-container-' + userId);
        const deptSelect = document.querySelector('.dept-select-' + userId);
        
        if (selectElement.value === 'dept_admin') {
            deptContainer.style.display = 'block';
            deptSelect.required = true;
        } else {
            deptContainer.style.display = 'none';
            deptSelect.required = false;
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>