<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('master_admin');

$page_title = 'Overview';
$active_tab = 'dashboard';
require_once __DIR__ . '/../includes/header.php';

// Fetch global metrics
$stmt = $pdo->query("SELECT COUNT(*) FROM applications");
$total_apps = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE overall_status = 'completed'");
$completed_apps = $stmt->fetchColumn();
$completion_rate = $total_apps > 0 ? round(($completed_apps / $total_apps) * 100) : 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE overall_status = 'in_progress'");
$in_progress = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE overall_status = 'action_required'");
$action_required = $stmt->fetchColumn();

// Fetch bottlenecks (departments with most pending statuses)
$stmt = $pdo->query("
    SELECT d.name, COUNT(*) as pending_count 
    FROM department_status ds 
    JOIN departments d ON ds.department_id = d.id 
    WHERE ds.status = 'pending' 
    GROUP BY d.id 
    ORDER BY pending_count DESC 
    LIMIT 6
");
$bottlenecks = $stmt->fetchAll();

// Search and filter applications
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$query = "
    SELECT a.id, a.user_id, a.overall_status, a.is_emergency, a.created_at, u.full_name, u.email, u.course, u.batch
    FROM applications a
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.course LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $query .= " AND a.overall_status = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY a.is_emergency DESC, a.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();
?>

<div class="mb-6">
    <h1 class="card-title text-2xl">Overview</h1>
    <p class="text-muted">Global metrics, search, and clearance management.</p>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.5rem;">
        <div class="flex items-center gap-4">
            <div style="background-color: var(--surface-2); padding: 0.75rem; border-radius: var(--radius-md);">
                <i data-lucide="users" style="width: 24px; height: 24px; color: var(--foreground);"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-muted mb-1">Total applications</p>
                <h3 class="text-2xl font-bold"><?php echo $total_apps; ?></h3>
            </div>
        </div>
    </div>
    <div class="card" style="padding: 1.5rem;">
        <div class="flex items-center gap-4">
            <div style="background-color: var(--status-approved-bg); padding: 0.75rem; border-radius: var(--radius-md);">
                <i data-lucide="file-check-2" style="width: 24px; height: 24px; color: var(--status-approved);"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-muted mb-1">Completion rate</p>
                <h3 class="text-2xl font-bold"><?php echo $completion_rate; ?>%</h3>
            </div>
        </div>
    </div>
    <div class="card" style="padding: 1.5rem;">
        <div class="flex items-center gap-4">
            <div style="background-color: color-mix(in srgb, var(--status-pending) 15%, transparent); padding: 0.75rem; border-radius: var(--radius-md);">
                <i data-lucide="clock" style="width: 24px; height: 24px; color: var(--status-pending);"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-muted mb-1">In progress</p>
                <h3 class="text-2xl font-bold"><?php echo $in_progress; ?></h3>
            </div>
        </div>
    </div>
    <div class="card" style="padding: 1.5rem;">
        <div class="flex items-center gap-4">
            <div style="background-color: var(--destructive-foreground); padding: 0.75rem; border-radius: var(--radius-md);">
                <i data-lucide="triangle-alert" style="width: 24px; height: 24px; color: var(--destructive);"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-muted mb-1">Action required</p>
                <h3 class="text-2xl font-bold"><?php echo $action_required; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Applications Panel -->
    <div class="card">
        <div class="card-header border-b border-border flex items-center justify-between" style="padding-bottom: 1rem;">
            <h2 class="card-title text-lg">Applications</h2>
            <form action="dashboard.php" method="GET" class="flex gap-2">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <div style="position: relative;">
                    <i data-lucide="search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted-foreground);"></i>
                    <input type="text" name="search" class="input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 2.25rem; height: 2rem;">
                </div>
                <select name="status" class="input" style="height: 2rem; padding: 0 0.5rem;" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="action_required" <?php echo $status_filter === 'action_required' ? 'selected' : ''; ?>>Action Required</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
                <?php if ($search || $status_filter): ?>
                    <a href="dashboard.php" class="btn btn-ghost" style="height: 2rem; padding: 0 0.5rem;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-content" style="padding: 0;">
            <?php if (count($applications) === 0): ?>
                <div class="text-center py-8 text-muted">No applications found.</div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <a href="student-details.php?id=<?php echo $app['user_id']; ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit; transition: background-color 0.2s;">
                        <div>
                            <div class="font-semibold flex items-center gap-2 mb-1">
                                <?php echo htmlspecialchars($app['full_name']); ?>
                                <?php if ($app['is_emergency']): ?>
                                    <span class="status-badge status-emergency" style="font-size: 0.65rem; padding: 0.125rem 0.375rem;">Urgent</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm text-muted"><?php echo htmlspecialchars($app['course'] . ' · ' . $app['batch']); ?> · <?php echo htmlspecialchars($app['email']); ?></div>
                        </div>
                        <div>
                            <?php if ($app['overall_status'] === 'completed'): ?>
                                <span class="status-badge status-approved">Completed</span>
                            <?php elseif ($app['overall_status'] === 'action_required'): ?>
                                <span class="status-badge status-denied">Action Required</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">In Progress</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottlenecks Panel -->
    <div class="flex-col gap-4">
        <div class="card">
            <div class="card-header border-b border-border" style="padding-bottom: 1rem;">
                <h2 class="card-title text-lg">Department bottlenecks</h2>
                <p class="card-description">Where pending requests pile up.</p>
            </div>
            <div class="card-content flex-col gap-2" style="padding-top: 1rem;">
                <?php if (count($bottlenecks) === 0): ?>
                    <div class="text-sm text-muted">No pending bottlenecks.</div>
                <?php else: ?>
                    <?php foreach ($bottlenecks as $b): ?>
                        <div class="flex items-center justify-between" style="padding: 0.5rem 0; border-bottom: 1px dashed var(--border);">
                            <span class="font-medium text-sm"><?php echo htmlspecialchars($b['name']); ?></span>
                            <span class="font-semibold text-sm" style="background: var(--surface-2); padding: 0.125rem 0.5rem; border-radius: 9999px;"><?php echo $b['pending_count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>