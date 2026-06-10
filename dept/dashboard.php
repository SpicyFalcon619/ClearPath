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
?>
<?php 
$page_title = 'Department Queue';
$active_tab = 'dashboard';
require_once __DIR__ . '/../includes/header.php'; 
?>

<div class="mb-6">
    <h1 class="card-title text-2xl">Department Queue</h1>
    <p class="text-muted">Review and act on clearance requests assigned to your department.</p>
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
    <div class="flex-col gap-4">
        <?php foreach ($applications as $app): ?>
        <div class="card" style="<?php echo $app['is_emergency'] ? 'border-left: 4px solid var(--status-emergency);' : ''; ?>">
            <div class="card-content" style="padding: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 class="font-semibold text-lg flex items-center gap-2 mb-1">
                        <?php echo htmlspecialchars($app['full_name']); ?>
                        <?php if ($app['is_emergency']): ?>
                            <span class="status-badge status-emergency">Urgent</span>
                        <?php endif; ?>
                    </h3>
                    <p class="text-sm font-medium mb-1"><?php echo htmlspecialchars($app['course'] . ' · ' . $app['batch']); ?> <span class="text-muted font-normal">· <?php echo htmlspecialchars($app['email']); ?></span></p>
                    <p class="text-xs text-muted">Submitted: <?php echo date('M d, Y g:i A', strtotime($app['created_at'])); ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($app['status'] === 'pending'): ?>
                        <span class="status-badge status-pending">Pending</span>
                    <?php elseif ($app['status'] === 'approved'): ?>
                        <span class="status-badge status-approved">Approved</span>
                    <?php else: ?>
                        <span class="status-badge status-denied">Denied</span>
                    <?php endif; ?>
                    <button type="button" onclick="openReviewModal(<?php echo $app['app_id']; ?>)" class="btn btn-outline">Review</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
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
