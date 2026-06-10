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
        $dept_id = (int)$_POST['dept_id'];
        $is_active = isset($_POST['active']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE departments SET active = ? WHERE id = ?");
        $stmt->execute([$is_active, $dept_id]);
    }
}

$stmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmt->fetchAll();

$page_title = 'Departments';
$active_tab = 'departments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
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

<div class="card mb-8">
    <div class="card-header border-b border-border" style="padding-bottom: 1rem;">
        <h3 class="card-title text-lg">Add department</h3>
    </div>
    <div class="card-content" style="padding-top: 1rem;">
        <form action="departments.php" method="POST" class="flex items-end gap-4">
            <input type="hidden" name="action" value="add_dept">
            <div class="form-group mb-0" style="flex: 1;">
                <label class="label text-sm" for="dept-name">Name</label>
                <input class="input" type="text" name="name" id="dept-name" placeholder="e.g., Transport" required>
            </div>
            <div class="form-group mb-0" style="flex: 2;">
                <label class="label text-sm" for="dept-desc">Description (optional)</label>
                <input class="input" type="text" name="description" id="dept-desc" placeholder="What does this department clear?">
            </div>
            <button type="submit" class="btn btn-primary" style="height: 2.5rem;"><i data-lucide="plus" style="width: 16px; height: 16px;"></i> Add</button>
        </form>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
    <?php foreach ($departments as $dept): ?>
        <div class="card flex items-center justify-between" style="padding: 1rem 1.5rem; <?php echo !$dept['active'] ? 'opacity: 0.6;' : ''; ?>">
            <div>
                <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($dept['name']); ?></h4>
                <p class="text-sm text-muted mt-1"><?php echo htmlspecialchars($dept['description'] ?: 'No description'); ?></p>
            </div>
            <form action="departments.php" method="POST" class="flex flex-col items-center gap-1">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                <span class="text-xs font-medium <?php echo $dept['active'] ? 'text-primary' : 'text-muted'; ?>">
                    <?php echo $dept['active'] ? 'Active' : 'Inactive'; ?>
                </span>
                <label style="position: relative; display: inline-block; width: 40px; height: 22px;">
                    <input type="checkbox" name="active" value="1" <?php echo $dept['active'] ? 'checked' : ''; ?> onchange="this.form.submit()" style="opacity: 0; width: 0; height: 0;">
                    <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $dept['active'] ? 'var(--primary)' : 'var(--input)'; ?>; border-radius: 34px; transition: .4s;"></span>
                    <span style="position: absolute; content: ''; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: .4s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transform: <?php echo $dept['active'] ? 'translateX(18px)' : 'translateX(0)'; ?>;"></span>
                </label>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
