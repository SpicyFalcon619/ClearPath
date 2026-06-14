<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('master_admin');

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

// Fetch Outstanding Dues total
$stmt = $pdo->query("SELECT SUM(amount) FROM clearance_items WHERE status = 'outstanding'");
$total_due_amount = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM clearance_items WHERE status = 'outstanding'");
$total_due_count = $stmt->fetchColumn();

// Fetch bottlenecks
$stmt = $pdo->query("
    SELECT d.name, COUNT(*) as pending_count 
    FROM department_status ds 
    JOIN departments d ON ds.department_id = d.id 
    WHERE ds.status = 'pending' 
    GROUP BY d.id 
    ORDER BY pending_count DESC
");
$bottlenecks = $stmt->fetchAll();

// Search and filter applications
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'all');

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
if ($status_filter !== 'all') {
    $query .= " AND a.overall_status = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY a.is_emergency DESC, a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

$page_title = 'Overview';
$active_tab = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 animate-fade-in" style="max-width: 64rem; display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="flex flex-wrap justify-between gap-3 items-end">
        <div>
            <h1 class="text-2xl font-semibold">Overview</h1>
            <p class="text-sm text-muted">Global metrics, dues, and clearance management.</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
        <div class="card" style="flex: 1 1 180px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--secondary); color: var(--foreground);">
                <i data-lucide="users" style="width: 16px; height: 16px;"></i>
            </div>
            <div style="min-width: 0;">
                <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total applications</div>
                <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';"><?php echo $total_apps; ?></div>
            </div>
        </div>
        
        <div class="card" style="flex: 1 1 180px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--secondary); color: var(--foreground);">
                <i data-lucide="file-check-2" style="width: 16px; height: 16px;"></i>
            </div>
            <div style="min-width: 0;">
                <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Completion rate</div>
                <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';"><?php echo $completion_rate; ?>%</div>
            </div>
        </div>

        <div class="card" style="flex: 1 1 180px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--secondary); color: var(--foreground);">
                <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
            </div>
            <div style="min-width: 0;">
                <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">In progress</div>
                <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';"><?php echo $in_progress; ?></div>
            </div>
        </div>

        <div class="card" style="flex: 1 1 180px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--status-denied-bg); color: var(--status-denied);">
                <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
            </div>
            <div style="min-width: 0;">
                <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Action required</div>
                <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';"><?php echo $action_required; ?></div>
            </div>
        </div>

        <div class="card" style="flex: 1 1 180px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--status-denied-bg); color: var(--status-denied);">
                <i data-lucide="wallet" style="width: 16px; height: 16px;"></i>
            </div>
            <div style="min-width: 0;">
                <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total Dues</div>
                <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';">৳<?php echo number_format($total_due_amount); ?></div>
                <div style="font-size: 10px; color: var(--muted-foreground);"><?php echo $total_due_count; ?> items</div>
            </div>
        </div>

    </div>

    <div class="grid lg:grid-cols-2 gap-6" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
        <!-- Bottlenecks Card -->
        <div class="card flex-col">
            <div class="card-header border-b border-border" style="padding: 1.25rem;">
                <h3 class="card-title text-base">Department bottlenecks</h3>
                <p class="text-xs text-muted mt-1">Where pending requests pile up.</p>
            </div>
            <div class="card-content" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <?php if (count($bottlenecks) === 0): ?>
                    <div class="flex items-center gap-2 text-sm" style="color: var(--status-approved); background: var(--status-approved-bg); border-radius: var(--radius-md); padding: 0.5rem;">
                        <i data-lucide="sparkles" style="width: 16px; height: 16px;"></i> All clear — no pending requests anywhere.
                    </div>
                <?php else: ?>
                    <?php foreach ($bottlenecks as $b): ?>
                        <div class="flex items-center justify-between text-sm" style="border: 1px solid var(--border); background: var(--surface-1); border-radius: var(--radius-md); padding: 0.5rem 0.75rem;">
                            <span><?php echo htmlspecialchars($b['name']); ?></span>
                            <span class="font-medium" style="font-feature-settings: 'tnum';"><?php echo $b['pending_count']; ?> pending</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Applications List -->
    <div class="card">
        <div class="card-header" style="padding: 1.25rem;">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h3 class="card-title text-base">Applications</h3>
                <form action="dashboard.php" method="GET" class="flex gap-2">
                    <div style="position: relative;">
                        <i data-lucide="search" style="position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted-foreground);"></i>
                        <input type="text" name="search" class="input" placeholder="Search name, email..." value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 2rem; width: 16rem;">
                    </div>
                    <select name="status" class="select" onchange="this.form.submit()" style="width: 10rem;">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="action_required" <?php echo $status_filter === 'action_required' ? 'selected' : ''; ?>>Action Required</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </form>
            </div>
        </div>
        <div class="card-content" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
            <?php if (count($applications) === 0): ?>
                <div class="text-center" style="padding: 3rem 1rem;">
                    <i data-lucide="<?php echo $search || $status_filter !== 'all' ? 'search' : 'inbox'; ?>" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: var(--muted-foreground); opacity: 0.5;"></i>
                    <h3 class="text-lg font-medium"><?php echo $search || $status_filter !== 'all' ? 'No applications match your filter' : 'No applications yet'; ?></h3>
                    <p class="text-sm text-muted mt-1"><?php echo $search || $status_filter !== 'all' ? 'Try clearing the search or changing the status filter.' : 'Once students submit applications, they will appear here.'; ?></p>
                    <?php if ($search || $status_filter !== 'all'): ?>
                        <a href="dashboard.php" class="btn btn-outline mt-4 inline-flex">Reset filters</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <a href="student-details.php?id=<?php echo $app['user_id']; ?>" class="flex flex-wrap items-center justify-between gap-3" style="display: flex; text-decoration: none; border: 1px solid var(--border); background: var(--surface-1); border-radius: var(--radius-lg); padding: 0.75rem; transition: background 0.2s;" onmouseover="this.style.background='var(--secondary)'" onmouseout="this.style.background='var(--surface-1)'">
                        <div style="min-width: 0;">
                            <div class="font-medium text-sm flex items-center gap-2 mb-0.5" style="color: var(--foreground);">
                                <?php echo htmlspecialchars($app['full_name'] ?: 'Student'); ?>
                                <?php if ($app['is_emergency']): ?>
                                    <span class="status-badge status-emergency" style="font-size: 0.65rem; padding: 0.125rem 0.375rem;">Urgent</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($app['course']); ?> · <?php echo htmlspecialchars($app['batch']); ?> · <?php echo htmlspecialchars($app['email']); ?>
                            </div>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>