<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('dept_admin');

// Fetch user and department
$stmt = $pdo->prepare("
    SELECT u.email, d.name as dept_name, d.id as dept_id
    FROM users u 
    JOIN departments d ON u.department_id = d.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$tab = $_GET['tab'] ?? 'pending';
$valid_tabs = ['pending', 'approved', 'denied'];
if (!in_array($tab, $valid_tabs)) {
    $tab = 'pending';
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['app_ids']) && is_array($_POST['app_ids'])) {
    $action = $_POST['bulk_action'];
    $app_ids = $_POST['app_ids'];
    $new_status = $action === 'approve' ? 'approved' : 'denied';

    try {
        $pdo->beginTransaction();
        
        $update_status_stmt = $pdo->prepare("UPDATE department_status SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ? AND department_id = ?");
        $log_stmt = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action) VALUES (?, ?, ?, ?)");
        $overall_status_check = $pdo->prepare("SELECT status FROM department_status WHERE application_id = ?");
        $overall_update = $pdo->prepare("UPDATE applications SET overall_status = ? WHERE id = ?");
        $get_outstanding_stmt = $pdo->prepare("SELECT COUNT(*) FROM clearance_items WHERE application_id = ? AND department_id = ? AND status = 'outstanding'");
        
        foreach ($app_ids as $app_id) {
            // Check for outstanding items if approving
            if ($new_status === 'approved') {
                $get_outstanding_stmt->execute([$app_id, $user['dept_id']]);
                if ($get_outstanding_stmt->fetchColumn() > 0) {
                    continue; // Skip approval if outstanding items exist
                }
            }

            $update_status_stmt->execute([$new_status, $_SESSION['user_id'], $app_id, $user['dept_id']]);
            $log_stmt->execute([$app_id, $user['dept_id'], $_SESSION['user_id'], $new_status]);
            
            $overall_status_check->execute([$app_id]);
            $all_statuses = $overall_status_check->fetchAll(PDO::FETCH_COLUMN);
            $overall = 'completed';
            foreach ($all_statuses as $st) {
                if ($st === 'denied') { $overall = 'action_required'; break; }
                elseif ($st === 'pending') { $overall = 'in_progress'; }
            }
            $overall_update->execute([$overall, $app_id]);
        }
        
        $pdo->commit();
        header("Location: dashboard.php?tab=$tab");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Bulk action failed: " . $e->getMessage();
    }
}

// Fetch applications for this department with the selected status
$stmt = $pdo->prepare("
    SELECT 
        a.id as app_id, a.created_at, a.is_emergency,
        u.full_name, u.email, u.course, u.batch,
        ds.id as ds_id, ds.status
    FROM department_status ds
    JOIN applications a ON ds.application_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE ds.department_id = ? AND ds.status = ?
    ORDER BY a.is_emergency DESC, a.created_at ASC
");
$stmt->execute([$user['dept_id'], $tab]);
$applications = $stmt->fetchAll();

// Get counts for tabs
$counts = ['pending' => 0, 'approved' => 0, 'denied' => 0];
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM department_status 
    WHERE department_id = ? 
    GROUP BY status
");
$stmt->execute([$user['dept_id']]);
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = $row['count'];
}

// Students with pending items
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT application_id) 
    FROM clearance_items 
    WHERE department_id = ? AND status = 'outstanding'
");
$stmt->execute([$user['dept_id']]);
$students_pending_items = $stmt->fetchColumn();

// Outstanding amount
$stmt = $pdo->prepare("
    SELECT SUM(amount) 
    FROM clearance_items 
    WHERE department_id = ? AND status = 'outstanding'
");
$stmt->execute([$user['dept_id']]);
$outstanding_amount = $stmt->fetchColumn() ?: 0;
?>
<?php 
$page_title = 'Department Queue';
$active_tab = 'dashboard';
require_once __DIR__ . '/../includes/header.php'; 
?>

<div class="mb-6">
    <h1 class="card-title text-2xl mb-1">Department Queue</h1>
    <p class="text-muted text-sm">Review requests, manage dues/obligations, and chat with students.</p>
</div>

<?php if (isset($error)): ?>
    <div class="card mb-6" style="background: color-mix(in srgb, var(--status-emergency) 10%, transparent); border-color: color-mix(in srgb, var(--status-emergency) 30%, transparent); padding: 1rem; color: var(--status-emergency);">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 2rem;">
    <div class="card" style="flex: 1 1 200px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--secondary); color: var(--foreground);">
            <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
        </div>
        <div style="min-width: 0;">
            <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Pending requests</div>
            <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';"><?php echo $counts['pending']; ?></div>
        </div>
    </div>
    <div class="card" style="flex: 1 1 200px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--secondary); color: var(--foreground);">
            <i data-lucide="users" style="width: 16px; height: 16px;"></i>
        </div>
        <div style="min-width: 0;">
            <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Students with pending items</div>
            <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';"><?php echo $students_pending_items; ?></div>
        </div>
    </div>
    <div class="card" style="flex: 1 1 200px; padding: 1rem; display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--status-emergency-bg); color: var(--status-emergency);">
            <i data-lucide="banknote" style="width: 16px; height: 16px;"></i>
        </div>
        <div style="min-width: 0;">
            <div class="text-xs text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total due</div>
            <div class="text-xl font-semibold" style="font-feature-settings: 'tnum';">BDT <?php echo number_format($outstanding_amount); ?></div>
        </div>
    </div>
</div>

<div class="tab-bar mb-6">
    <a href="dashboard.php?tab=pending" class="tab-item <?php echo $tab === 'pending' ? 'active' : ''; ?>">Pending (<?php echo $counts['pending']; ?>)</a>
    <a href="dashboard.php?tab=approved" class="tab-item <?php echo $tab === 'approved' ? 'active' : ''; ?>">Approved (<?php echo $counts['approved']; ?>)</a>
    <a href="dashboard.php?tab=denied" class="tab-item <?php echo $tab === 'denied' ? 'active' : ''; ?>">Denied (<?php echo $counts['denied']; ?>)</a>
</div>

<?php if (count($applications) === 0): ?>
    <div class="card" style="text-align: center; padding: 4rem 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i data-lucide="inbox" style="width: 48px; height: 48px; color: var(--muted-foreground); margin-bottom: 1rem;"></i>
        <h2 class="card-title mb-2">Queue is empty</h2>
        <p class="card-description">No applications found in this category.</p>
    </div>
<?php else: ?>
    <form method="POST" action="dashboard.php?tab=<?php echo $tab; ?>">
        <input type="hidden" name="bulk_action" id="bulk-action-type" value="">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-left: 0.5rem; min-height: 32px;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" id="select-all" onclick="document.querySelectorAll('.app-checkbox').forEach(cb => { cb.checked = this.checked; }); toggleBulkActions();" style="width: 16px; height: 16px; cursor: pointer;">
                <span class="text-sm text-muted">Select all (<?php echo count($applications); ?>)</span>
            </label>
            <div id="bulk-actions" style="display: none; gap: 0.5rem;">
                <button type="button" onclick="submitBulk('approve')" class="btn btn-outline-success" style="height: 2rem; padding: 0 1rem; font-size: 0.875rem;"><i data-lucide="check" style="width: 14px; height: 14px; margin-right: 4px;"></i> Approve Selected</button>
                <button type="button" onclick="submitBulk('deny')" class="btn btn-outline-danger" style="height: 2rem; padding: 0 1rem; font-size: 0.875rem;"><i data-lucide="x" style="width: 14px; height: 14px; margin-right: 4px;"></i> Deny Selected</button>
            </div>
        </div>
        
        <script>
            function toggleBulkActions() {
                const checkedCount = document.querySelectorAll('.app-checkbox:checked').length;
                document.getElementById('bulk-actions').style.display = checkedCount > 0 ? 'flex' : 'none';
                
                const allCheckboxes = document.querySelectorAll('.app-checkbox');
                document.getElementById('select-all').checked = checkedCount === allCheckboxes.length && allCheckboxes.length > 0;
            }
            function submitBulk(action) {
                if (confirm(`Are you sure you want to ${action} the selected students?`)) {
                    document.getElementById('bulk-action-type').value = action;
                    document.forms[0].submit();
                }
            }
        </script>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($applications as $app): ?>
            <?php
                // Check if this student has pending items
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(amount) as total FROM clearance_items WHERE application_id = ? AND department_id = ? AND status = 'outstanding'");
                $stmt->execute([$app['app_id'], $user['dept_id']]);
                $due_row = $stmt->fetch();
                $pending_count = $due_row['cnt'] ?: 0;
                $student_dues = $due_row['total'] ?: 0;
            ?>
            <div class="card" style="display: flex; flex-direction: row; align-items: center; <?php echo $app['is_emergency'] ? 'border-left: 4px solid var(--status-emergency);' : ''; ?>">
                <div style="padding: 0.75rem 0 0.75rem 1rem;">
                    <input type="checkbox" name="app_ids[]" value="<?php echo $app['app_id']; ?>" class="app-checkbox" onclick="toggleBulkActions()" style="width: 16px; height: 16px; cursor: pointer;">
                </div>
                <div class="card-content" style="padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; flex: 1;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div>
                            <h3 class="font-semibold text-sm flex items-center gap-2 mb-1">
                                <?php echo htmlspecialchars($app['full_name']); ?>
                                <?php if ($pending_count > 0): ?>
                                    <span style="font-size: 0.65rem; font-weight: 600; color: var(--status-emergency); background: color-mix(in srgb, var(--status-emergency) 15%, transparent); padding: 0.125rem 0.375rem; border-radius: 9999px; border: 1px solid color-mix(in srgb, var(--status-emergency) 30%, transparent);">
                                        <?php echo $pending_count; ?> pending
                                        <?php if ($student_dues > 0): ?>
                                            · BDT <?php echo number_format($student_dues); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <p class="text-xs font-medium text-muted">
                                <?php if (!empty($app['course']) || !empty($app['batch'])): ?>
                                    <?php echo htmlspecialchars(trim($app['course'] . ' ' . $app['batch'])); ?> <span class="font-normal">·</span> 
                                <?php endif; ?>
                                <span class="font-normal"><?php echo htmlspecialchars($app['email']); ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if ($app['status'] === 'pending'): ?>
                            <span class="status-badge status-pending" style="background: transparent; border: 1px solid var(--status-pending); color: var(--status-pending);">
                                <i data-lucide="clock" style="width: 12px; height: 12px; margin-right: 4px;"></i> Pending
                            </span>
                        <?php elseif ($app['status'] === 'approved'): ?>
                            <span class="status-badge status-approved" style="background: transparent; border: 1px solid var(--status-approved); color: var(--status-approved);">
                                <i data-lucide="check" style="width: 12px; height: 12px; margin-right: 4px;"></i> Approved
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-denied" style="background: transparent; border: 1px solid var(--status-emergency); color: var(--status-emergency);">
                                <i data-lucide="x" style="width: 12px; height: 12px; margin-right: 4px;"></i> Denied
                            </span>
                        <?php endif; ?>
                        <button type="button" onclick="openReviewModal(<?php echo $app['app_id']; ?>)" class="btn btn-primary" style="padding: 0.25rem 1rem; height: 2rem;">Review</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
<?php endif; ?>

<div id="global-modal" style="display: none; position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 1rem;">
    <div id="global-modal-content" style="background: var(--surface-1); width: 100%; max-width: 850px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow-lg);">
        <!-- Modal content loaded via AJAX -->
    </div>
</div>

<script>
    function openReviewModal(appId) {
        const modal = document.getElementById('global-modal');
        const content = document.getElementById('global-modal-content');
        
        modal.style.display = 'flex';
        content.innerHTML = '<div style="padding: 3rem; text-align: center; color: var(--muted-foreground);"><i data-lucide="loader-2" class="lucide-spin" style="width: 32px; height: 32px; margin: 0 auto 1rem auto;"></i><p>Loading...</p></div>';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        fetch('review_modal_content.php?id=' + appId)
            .then(res => res.text())
            .then(html => {
                content.innerHTML = html;
                if (typeof lucide !== 'undefined') lucide.createIcons();
                
                const scripts = content.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.text = script.text;
                    document.body.appendChild(newScript).parentNode.removeChild(newScript);
                });
            });
    }

    function closeReviewModal() {
        document.getElementById('global-modal').style.display = 'none';
        document.getElementById('global-modal-content').innerHTML = '';
    }

    function submitReviewModal(event, form) {
        event.preventDefault();
        
        const formData = new FormData(form);
        const submitter = event.submitter;
        if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value);
        }

        const btns = form.querySelectorAll('button[type="submit"]');
        btns.forEach(b => { b.disabled = true; b.style.opacity = '0.5'; });

        fetch(form.getAttribute('action'), {
            method: form.getAttribute('method') || 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(html => {
            if (html.includes('window.location.reload()')) {
                window.location.reload();
                return;
            }

            const content = document.getElementById('global-modal-content');
            content.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            const scripts = content.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                newScript.text = script.text;
                document.body.appendChild(newScript).parentNode.removeChild(newScript);
            });
        });
    }
</script>
<style>
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .lucide-spin { animation: spin 1s linear infinite; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
