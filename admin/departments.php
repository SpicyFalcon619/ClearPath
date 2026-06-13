<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('master_admin');

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_dept') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (name, description, active) VALUES (?, ?, 1)");
                if ($stmt->execute([$name, $description])) {
                    $success_msg = "Department added successfully.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to add department. It might already exist.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
        $dept_id = $_POST['dept_id'];
        $active = isset($_POST['active']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE departments SET active = ? WHERE id = ?");
        if ($stmt->execute([$active, $dept_id])) {
            $success_msg = "Department status updated.";
        } else {
            $error_msg = "Failed to update department status.";
        }
        
        // Handle AJAX request gracefully
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_dept') {
        $dept_id = (int)$_POST['dept_id'];
        
        // Check if there are existing clearance records for this department
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM department_status WHERE department_id = ?");
        $stmt->execute([$dept_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error_msg = "Cannot delete department — it's referenced by $count clearance record(s). Mark it inactive instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            if ($stmt->execute([$dept_id])) {
                $success_msg = "Department deleted successfully.";
            } else {
                $error_msg = "Failed to delete department.";
            }
        }
    }
}

$stmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmt->fetchAll();

$page_title = 'Departments';
$active_tab = 'departments';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 48rem; display: flex; flex-direction: column; gap: 1.5rem;">
    <div>
        <h1 class="card-title text-2xl">Departments</h1>
        <p class="text-muted">Configure which departments route clearance requests.</p>
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

    <div class="card mb-6">
        <div class="card-header border-b border-border" style="padding-bottom: 1rem;">
            <h3 class="card-title text-base">Add department</h3>
        </div>
        <div class="card-content" style="padding-top: 1rem;">
            <form action="departments.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="add_dept">
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group mb-0">
                        <label class="label text-sm" for="dept-name">Name</label>
                        <input class="input" type="text" name="name" id="dept-name" placeholder="e.g., Transport" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="label text-sm" for="dept-desc">Description</label>
                        <input class="input" type="text" name="description" id="dept-desc" placeholder="Optional">
                    </div>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 2.5rem;"><i data-lucide="plus" style="width: 16px; height: 16px;"></i> Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <?php foreach ($departments as $dept): ?>
            <div class="card flex flex-wrap items-center justify-between gap-4" style="padding: 0.75rem 1.25rem; <?php echo !$dept['active'] ? 'opacity: 0.7;' : ''; ?>">
                <div>
                    <h4 class="font-medium text-base"><?php echo htmlspecialchars($dept['name']); ?></h4>
                    <?php if ($dept['description']): ?>
                        <p class="text-xs text-muted mt-0.5"><?php echo htmlspecialchars($dept['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-muted" id="status-text-<?php echo $dept['id']; ?>">
                        <?php echo $dept['active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <label style="position: relative; display: inline-block; width: 36px; height: 20px; margin-right: 0.5rem; cursor: pointer;">
                        <input type="checkbox" onchange="toggleDeptActive(<?php echo $dept['id']; ?>, this.checked)" <?php echo $dept['active'] ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                        <span id="toggle-bg-<?php echo $dept['id']; ?>" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $dept['active'] ? 'var(--primary)' : 'var(--input)'; ?>; border-radius: 34px; transition: .2s;"></span>
                        <span id="toggle-knob-<?php echo $dept['id']; ?>" style="position: absolute; content: ''; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: .2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transform: <?php echo $dept['active'] ? 'translateX(16px)' : 'translateX(0)'; ?>;"></span>
                    </label>
                    
                    <form action="departments.php" method="POST" style="margin: 0; display: flex; align-items: center;" onsubmit="return confirm('Delete \'<?php echo htmlspecialchars($dept['name'], ENT_QUOTES); ?>\'? This permanently removes the department if it has no clearance records.')">
                        <input type="hidden" name="action" value="delete_dept">
                        <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                        <button type="submit" class="btn btn-ghost" style="color: var(--destructive); padding: 0.25rem 0.5rem; height: auto;" title="Delete department">
                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleDeptActive(deptId, isActive) {
    // Optimistic UI Update
    const statusText = document.getElementById('status-text-' + deptId);
    if (statusText) statusText.innerText = isActive ? 'Active' : 'Inactive';
    
    const bg = document.getElementById('toggle-bg-' + deptId);
    if (bg) bg.style.backgroundColor = isActive ? 'var(--primary)' : 'var(--input)';
    
    const knob = document.getElementById('toggle-knob-' + deptId);
    if (knob) knob.style.transform = isActive ? 'translateX(16px)' : 'translateX(0)';
    
    // Background AJAX Request
    const formData = new FormData();
    formData.append('action', 'toggle_active');
    formData.append('dept_id', deptId);
    if (isActive) formData.append('active', '1');
    
    fetch('departments.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).catch(err => console.error('Failed to update status:', err));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
