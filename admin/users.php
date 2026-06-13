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

<div style="max-width: 56rem; display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="card-title text-2xl">Users</h1>
            <p class="text-muted">All accounts — students and administrators. Search and assign roles.</p>
        </div>
        <form action="users.php" method="GET" style="position: relative; width: 250px;">
            <i data-lucide="search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted-foreground);"></i>
            <input type="text" name="search" class="input" placeholder="Search…" value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 2.25rem;">
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

    <div class="flex flex-col gap-4">
        <?php foreach ($users as $u): ?>
            <div class="space-y-2">
                <div class="card" id="card-<?php echo $u['id']; ?>" style="padding: 0.75rem 1.25rem; transition: all 0.2s;">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-sm flex items-center gap-2 mb-0.5">
                                <?php echo htmlspecialchars($u['full_name'] ?: '—'); ?>
                            </div>
                            <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;">
                                <?php echo htmlspecialchars($u['email']); ?>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <?php if ($u['role'] === 'master_admin'): ?>
                                <span style="font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 999px; border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent); background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary);">Master Admin</span>
                            <?php elseif ($u['role'] === 'dept_admin'): ?>
                                <span style="font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 999px; border: 1px solid color-mix(in srgb, var(--status-pending) 20%, transparent); background: var(--status-pending-bg); color: var(--status-pending);">Dept Admin · <?php echo htmlspecialchars($u['dept_name']); ?></span>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 999px; border: 1px solid var(--border); background: var(--secondary); color: var(--foreground);">Student</span>
                            <?php endif; ?>
                            
                            <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                <span style="font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 999px; border: 1px solid var(--border); background: var(--secondary); color: var(--muted-foreground);">You</span>
                            <?php else: ?>
                                <button class="btn btn-ghost" id="btn-edit-<?php echo $u['id']; ?>" style="height: 1.75rem; padding: 0 0.5rem; font-size: 0.75rem;" onclick="toggleEditMode(<?php echo $u['id']; ?>)">
                                    Edit role
                                </button>
                                
                                <form action="users.php" method="POST" style="margin: 0; display: inline-flex;" onsubmit="return confirm('Delete this user? This permanently removes <?php echo htmlspecialchars(addslashes($u['full_name'] ?: $u['email'])); ?> and their account. This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-ghost" style="color: var(--destructive); padding: 0 0.5rem; height: 1.75rem;" title="Delete user">
                                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="student-details.php?id=<?php echo $u['id']; ?>" class="btn btn-ghost" style="padding: 0 0.5rem; height: 1.75rem;"><i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Edit Form Dropdown (Matching Reference UI) -->
                <div id="edit-user-<?php echo $u['id']; ?>" class="card" style="display: none; margin-top: 0.5rem; margin-left: 1rem; border-color: color-mix(in srgb, var(--primary) 30%, transparent); background: color-mix(in srgb, var(--primary) 3%, transparent);">
                    <div class="card-content" style="padding: 1rem;">
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-sm font-medium">Edit role for <?php echo htmlspecialchars($u['full_name'] ?: $u['email']); ?></div>
                            <button class="btn btn-ghost" style="padding: 0; height: 1.75rem; width: 1.75rem;" onclick="toggleEditMode(<?php echo $u['id']; ?>)">
                                <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                            </button>
                        </div>
                        <form action="users.php" method="POST">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            
                            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="form-group mb-0">
                                    <label class="label text-sm" style="margin-bottom: 0.25rem;">Role</label>
                                    <select name="role" class="select role-select" data-userid="<?php echo $u['id']; ?>" onchange="toggleDeptSelect(this)">
                                        <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="dept_admin" <?php echo $u['role'] === 'dept_admin' ? 'selected' : ''; ?>>Department Admin</option>
                                        <option value="master_admin" <?php echo $u['role'] === 'master_admin' ? 'selected' : ''; ?>>Master Admin</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mb-0 dept-container-<?php echo $u['id']; ?>" style="display: <?php echo $u['role'] === 'dept_admin' ? 'block' : 'none'; ?>;">
                                    <label class="label text-sm" style="margin-bottom: 0.25rem;">Department</label>
                                    <select name="department_id" class="select dept-select-<?php echo $u['id']; ?>" <?php echo $u['role'] === 'dept_admin' ? 'required' : ''; ?>>
                                        <option value="">Select department</option>
                                        <?php foreach ($all_departments as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo $u['department_id'] == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost" style="height: 2.25rem; font-size: 0.875rem;" onclick="toggleEditMode(<?php echo $u['id']; ?>)">Cancel</button>
                                <button type="submit" class="btn btn-primary" style="height: 2.25rem; font-size: 0.875rem;">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($users) === 0): ?>
            <div class="card text-center py-12">
                <i data-lucide="users" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: var(--muted-foreground); opacity: 0.5;"></i>
                <h3 class="text-lg font-medium">No users match your search</h3>
                <p class="text-sm text-muted mt-1">Try a different name or email.</p>
                <a href="users.php" class="btn btn-outline mt-4 inline-flex">Clear search</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleEditMode(userId) {
        const editContainer = document.getElementById('edit-user-' + userId);
        const card = document.getElementById('card-' + userId);
        const editBtn = document.getElementById('btn-edit-' + userId);
        
        if (editContainer.style.display === 'none' || editContainer.style.display === '') {
            editContainer.style.display = 'block';
            card.style.boxShadow = '0 0 0 2px color-mix(in srgb, var(--primary) 40%, transparent)';
            editBtn.classList.replace('btn-ghost', 'btn-secondary');
            editBtn.innerText = 'Close';
        } else {
            editContainer.style.display = 'none';
            card.style.boxShadow = '';
            editBtn.classList.replace('btn-secondary', 'btn-ghost');
            editBtn.innerText = 'Edit role';
        }
    }

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