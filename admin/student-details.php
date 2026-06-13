<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('master_admin');

$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    header("Location: dashboard.php");
    exit();
}

// Handle master overrides
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'override') {
        $ds_id = $_POST['ds_id'];
        $new_status = $_POST['new_status']; // 'approved' or 'denied'
        $comment = trim($_POST['comment'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE department_status SET status = ?, comments = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $comment, $_SESSION['user_id'], $ds_id]);
        
        // Audit log
        $stmt = $pdo->prepare("SELECT application_id, department_id FROM department_status WHERE id = ?");
        $stmt->execute([$ds_id]);
        $ds = $stmt->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action, comments) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ds['application_id'], $ds['department_id'], $_SESSION['user_id'], 'master_override_' . $new_status, $comment]);
        
        // Notify student
        $stmt = $pdo->prepare("SELECT user_id FROM applications WHERE id = ?");
        $stmt->execute([$ds['application_id']]);
        $student_id_from_app = $stmt->fetchColumn();
        
        if ($student_id_from_app) {
            $msg = "A department status has been updated to " . strtoupper($new_status);
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
                ->execute([$student_id_from_app, $msg, '/clearpath/student/dashboard.php']);
        }
        
        // Evaluate overall status
        $stmt = $pdo->prepare("SELECT status FROM department_status WHERE application_id = ?");
        $stmt->execute([$ds['application_id']]);
        $all_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $overall = 'completed';
        foreach ($all_statuses as $st) {
            if ($st === 'denied') { $overall = 'action_required'; break; }
            elseif ($st === 'pending') { $overall = 'in_progress'; }
        }
        
        $pdo->prepare("UPDATE applications SET overall_status = ? WHERE id = ?")->execute([$overall, $ds['application_id']]);

        header("Location: student-details.php?id=" . $student_id);
        exit();
    } elseif ($_POST['action'] === 'add_item') {
        $app_id = $_POST['application_id'];
        $dept_id = $_POST['department_id'];
        $title = trim($_POST['title']);
        $kind = $_POST['kind'] ?? 'fee';
        $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : 0.00;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $description = trim($_POST['description'] ?? '');

        $stmt = $pdo->prepare("INSERT INTO clearance_items (user_id, department_id, application_id, title, amount, status, kind, due_date, description) VALUES (?, ?, ?, ?, ?, 'outstanding', ?, ?, ?)");
        $stmt->execute([$student_id, $dept_id, $app_id, $title, $amount, $kind, $due_date, $description]);

        $stmt = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action, comments) VALUES (?, ?, ?, 'item_added', ?)");
        $stmt->execute([$app_id, $dept_id, $_SESSION['user_id'], "Added item: $title"]);

        // Notify student
        $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
            ->execute([$student_id, "New item added: $title", '/clearpath/student/dashboard.php']);

        // Auto-revert department status to pending if it was approved
        $stmt = $pdo->prepare("UPDATE department_status SET status = 'pending' WHERE application_id = ? AND department_id = ? AND status = 'approved'");
        $stmt->execute([$app_id, $dept_id]);
        if ($stmt->rowCount() > 0) {
            $stmt2 = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action, comments) VALUES (?, ?, ?, 'status_reverted', ?)");
            $stmt2->execute([$app_id, $dept_id, $_SESSION['user_id'], "Auto-reverted to pending because a new item was added"]);
            
            // Auto-revert application overall status to action_required if it was completed
            $pdo->prepare("UPDATE applications SET overall_status = 'action_required' WHERE id = ? AND overall_status = 'completed'")->execute([$app_id]);
        }

        header("Location: student-details.php?id=" . $student_id);
        exit();
    } elseif ($_POST['action'] === 'toggle_item_status') {
        $item_id = $_POST['item_id'];
        $status = $_POST['status']; 
        
        $stmt = $pdo->prepare("SELECT title, application_id, department_id FROM clearance_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE clearance_items SET status = ? WHERE id = ?");
        $stmt->execute([$status, $item_id]);
        
        if ($item) {
            $stmt = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action, comments) VALUES (?, ?, ?, 'item_updated', ?)");
            $stmt->execute([$item['application_id'], $item['department_id'], $_SESSION['user_id'], "Marked item as " . strtoupper($status) . ": " . $item['title']]);
            
            // Notify student
            $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
                ->execute([$student_id, "Item updated to " . strtoupper($status) . ": " . $item['title'], '/clearpath/student/dashboard.php']);
        }
        
        header("Location: student-details.php?id=" . $student_id);
        exit();
    } elseif ($_POST['action'] === 'delete_item') {
        $item_id = $_POST['item_id'];
        
        $stmt = $pdo->prepare("SELECT title, application_id, department_id FROM clearance_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM clearance_items WHERE id = ?");
        $stmt->execute([$item_id]);
        
        if ($item) {
            $stmt = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action, comments) VALUES (?, ?, ?, 'item_deleted', ?)");
            $stmt->execute([$item['application_id'], $item['department_id'], $_SESSION['user_id'], "Deleted item: " . $item['title']]);
        }
        
        header("Location: student-details.php?id=" . $student_id);
        exit();
    } elseif ($_POST['action'] === 'approve_all') {
        $app_id = $_POST['application_id'];
        
        $stmt = $pdo->prepare("UPDATE department_status SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
        $stmt->execute([$_SESSION['user_id'], $app_id]);
        
        $pdo->prepare("UPDATE applications SET overall_status = 'completed' WHERE id = ?")->execute([$app_id]);
        
        $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action, comments) VALUES (?, NULL, ?, 'approve_all', ?)")
            ->execute([$app_id, $_SESSION['user_id'], "Master Admin approved all departments."]);
            
        // Notify student
        $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)")
            ->execute([$student_id, "Master Admin has approved all your department clearances.", '/clearpath/student/dashboard.php']);
            
        header("Location: student-details.php?id=" . $student_id);
        exit();
    }
}

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$profile = $stmt->fetch();

// Fetch applications
$stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$student_id]);
$apps = $stmt->fetchAll();

// Fetch departments
$stmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmt->fetchAll();

$page_title = 'Student Details';
$active_tab = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="animate-fade-in flex flex-col gap-6" style="max-width: 56rem;">
    <div>
        <a href="dashboard.php" class="btn btn-ghost" style="padding: 0 0.5rem; height: 2rem; display: inline-flex; margin-bottom: 0.5rem; margin-left: -0.5rem;">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px; margin-right: 0.25rem;"></i> Back
        </a>
        <h1 class="text-2xl font-semibold"><?php echo htmlspecialchars($profile['full_name'] ?? 'Student'); ?></h1>
        <p class="text-sm text-muted" style="margin-top: 0.25rem;"><?php echo htmlspecialchars($profile['email'] ?? ''); ?></p>
    </div>

    <?php if ($profile['role'] !== 'student'): ?>
        <div class="card p-6" style="background: var(--surface-1); border-left: 4px solid var(--primary);">
            <h3 class="text-lg font-semibold mb-1">Staff Profile</h3>
            <p class="text-sm text-muted">This user is an administrator (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role']))); ?>) and does not require clearance applications.</p>
        </div>
    <?php else: ?>
        <?php if (count($apps) === 0): ?>
            <div class="card p-8 text-center text-muted">No applications found for this student.</div>
        <?php endif; ?>

    <?php foreach ($apps as $app): ?>
        <?php
        // Fetch statuses for this app
        $stmt = $pdo->prepare("SELECT * FROM department_status WHERE application_id = ?");
        $stmt->execute([$app['id']]);
        $statuses = [];
        foreach ($stmt->fetchAll() as $s) {
            $statuses[$s['department_id']] = $s;
        }

        // Fetch docs
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ?");
        $stmt->execute([$app['id']]);
        $docs = $stmt->fetchAll();

        // Fetch items
        $stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE application_id = ? ORDER BY created_at ASC");
        $stmt->execute([$app['id']]);
        $items_by_dept = [];
        foreach ($stmt->fetchAll() as $item) {
            $items_by_dept[$item['department_id']][] = $item;
        }

        // Fetch audit
        $stmt = $pdo->prepare("SELECT a.*, d.name as dept_name, u.full_name as actor_name FROM audit_log a LEFT JOIN departments d ON a.department_id = d.id LEFT JOIN users u ON a.actor_id = u.id WHERE a.application_id = ? ORDER BY a.created_at DESC");
        $stmt->execute([$app['id']]);
        $audit = $stmt->fetchAll();
        ?>

        <div class="card" style="margin-top: 0.5rem;">
            <div class="card-content flex flex-col gap-6" style="padding: 1.5rem;">
                
                <!-- App Header section -->
                <div class="flex flex-col gap-3 pb-6 border-b border-border">
                    <div>
                        <h2 class="text-lg font-semibold flex items-center gap-2 mb-1">
                            <?php echo htmlspecialchars($profile['course'] . ' · ' . $profile['batch']); ?>
                            <?php if ($app['is_emergency']): ?>
                                <span class="status-badge status-emergency" style="font-size: 0.65rem; padding: 0.125rem 0.375rem;">Urgent</span>
                            <?php endif; ?>
                        </h2>
                        <p class="text-sm text-muted">Submitted <?php echo date('M d, Y h:i A', strtotime($app['created_at'])); ?></p>
                    </div>
                    
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-3">
                            <?php if ($app['overall_status'] === 'completed'): ?>
                                <span class="status-badge status-approved">Completed</span>
                            <?php elseif ($app['overall_status'] === 'action_required'): ?>
                                <span class="status-badge status-denied">Action Required</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">In Progress</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($app['overall_status'] === 'completed'): ?>
                            <a href="/clearpath/student/certificate.php?id=<?php echo $app['id']; ?>" target="_blank" class="btn btn-outline" style="height: 1.75rem; padding: 0 0.5rem; font-size: 0.75rem;">
                                <i data-lucide="download" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Certificate
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline" style="height: 1.75rem; padding: 0 0.5rem; font-size: 0.75rem;" disabled>
                                <i data-lucide="download" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Certificate
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($app['overall_status'] !== 'completed'): ?>
                <div class="mb-4">
                    <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to approve all departments for this application?');">
                        <input type="hidden" name="action" value="approve_all">
                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                        <button type="submit" class="btn btn-outline-success" style="width: 100%;">
                            <i data-lucide="check-check" style="width: 16px; height: 16px; margin-right: 0.5rem;"></i> Approve All Departments
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Departments List -->
                <div class="flex flex-col gap-4 pb-4 border-b border-border">
                    <?php foreach ($departments as $index => $dept): ?>
                        <?php 
                        $s = $statuses[$dept['id']] ?? null; 
                        $items = $items_by_dept[$dept['id']] ?? [];
                        ?>
                        <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem;">
                            <!-- Header Row -->
                            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($dept['name']); ?></div>
                                    <div class="text-xs text-muted" style="margin-top: 0.25rem;">Master admin override</div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <?php if ($s && $s['status'] === 'approved'): ?>
                                        <span class="status-badge status-approved" style="background: transparent; border: 1px solid var(--status-approved); color: var(--status-approved); padding: 0.125rem 0.5rem; height: 1.75rem; display: inline-flex; align-items: center;">
                                            <i data-lucide="check-circle-2" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Approved
                                        </span>
                                    <?php elseif ($s && $s['status'] === 'denied'): ?>
                                        <span class="status-badge status-denied" style="background: transparent; border: 1px solid var(--status-denied); color: var(--status-denied); padding: 0.125rem 0.5rem; height: 1.75rem; display: inline-flex; align-items: center;">
                                            <i data-lucide="x-circle" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Denied
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending" style="background: transparent; border: 1px solid color-mix(in srgb, var(--primary) 50%, transparent); color: var(--primary); padding: 0.125rem 0.5rem; height: 1.75rem; display: inline-flex; align-items: center;">
                                            <i data-lucide="clock" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Pending
                                        </span>
                                    <?php endif; ?>

                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <?php if ($s): ?>
                                            <?php if ($s['status'] === 'pending'): ?>
                                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="override">
                                                    <input type="hidden" name="ds_id" value="<?php echo $s['id']; ?>">
                                                    <input type="hidden" name="new_status" value="approved">
                                                    <button type="submit" class="btn btn-outline" style="height: 1.75rem; padding: 0 0.5rem; font-size: 0.75rem; border-color: var(--border);">
                                                        <i data-lucide="shield-check" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Approve
                                                    </button>
                                                </form>
                                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;" onsubmit="let r = prompt('Reason for denial?'); if(r){ this.comment.value = r; return true; } return false;">
                                                    <input type="hidden" name="action" value="override">
                                                    <input type="hidden" name="ds_id" value="<?php echo $s['id']; ?>">
                                                    <input type="hidden" name="new_status" value="denied">
                                                    <input type="hidden" name="comment" value="">
                                                    <button type="submit" class="btn btn-outline" style="height: 1.75rem; padding: 0 0.5rem; font-size: 0.75rem; border-color: var(--border);">
                                                        <i data-lucide="shield-x" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Deny
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="override">
                                                    <input type="hidden" name="ds_id" value="<?php echo $s['id']; ?>">
                                                    <input type="hidden" name="new_status" value="pending">
                                                    <input type="hidden" name="comment" value="">
                                                    <button type="submit" class="btn btn-outline" style="height: 1.75rem; padding: 0 0.5rem; font-size: 0.75rem; border-color: var(--border);">
                                                        <i data-lucide="rotate-ccw" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Reset to pending
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Dues & Obligations Header -->
                            <div class="flex items-center justify-between mt-6 mb-3">
                                <div>
                                    <h3 class="font-semibold text-sm">Dues & obligations</h3>
                                    <?php
                                        $total_due = 0;
                                        foreach($items as $i) { if($i['status'] === 'outstanding') $total_due += $i['amount']; }
                                    ?>
                                    <div class="text-xs text-muted" style="margin-top: 0.125rem;">
                                        <?php echo count($items); ?> items <?php if($total_due > 0) echo '· BDT ' . number_format($total_due, 0) . ' due'; ?>
                                    </div>
                                </div>
                                <button class="btn btn-outline" style="height: 1.75rem; padding: 0 0.75rem; font-size: 0.75rem; border-radius: 9999px; border-color: var(--border);" onclick="let f = document.getElementById('add-item-form-<?php echo $app['id']; ?>-<?php echo $dept['id']; ?>'); f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';">
                                    <i data-lucide="plus" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Add item
                                </button>
                            </div>
                            
                            <!-- Add Item Form -->
                            <div id="add-item-form-<?php echo $app['id']; ?>-<?php echo $dept['id']; ?>" style="display: none; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" class="flex flex-col gap-4">
                                    <input type="hidden" name="action" value="add_item">
                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                    
                                    <div class="grid" style="grid-template-columns: 1fr 2fr; gap: 1rem;">
                                        <div class="form-group mb-0">
                                            <label class="label text-sm">Type</label>
                                            <select name="kind" class="select" required onchange="
                                                let form = this.closest('form');
                                                let amtInput = form.querySelector('input[name=amount]');
                                                let amtLabel = form.querySelector('.amount-label');
                                                if (this.value === 'fee') {
                                                    amtInput.required = true;
                                                    amtLabel.innerText = 'Amount *';
                                                } else {
                                                    amtInput.required = false;
                                                    amtLabel.innerText = 'Amount (optional)';
                                                }
                                            ">
                                                <option value="fee">Fee / Money</option>
                                                <option value="book">Book</option>
                                                <option value="equipment">Equipment</option>
                                                <option value="document">Document</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="label text-sm">Title</label>
                                            <input type="text" name="title" class="input" placeholder="e.g. Tuition Spring 2026" required>
                                        </div>
                                    </div>
                                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <div class="form-group mb-0">
                                            <label class="label text-sm amount-label">Amount *</label>
                                            <input type="number" step="0.01" name="amount" class="input" placeholder="0.00" required>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="label text-sm">Due date (optional)</label>
                                            <input type="date" name="due_date" class="input">
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="label text-sm">Notes (optional)</label>
                                        <textarea name="description" class="input" placeholder="Extra detail visible to the student" style="min-height: 4rem;"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-item-form-<?php echo $app['id']; ?>-<?php echo $dept['id']; ?>').style.display = 'none';">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Items List -->
                            <?php if (count($items) > 0): ?>
                            <div class="flex flex-col gap-2">
                                <?php foreach ($items as $item): ?>
                                    <?php
                                        $icon = 'circle-dot';
                                        if ($item['kind'] === 'Fee / Money') $icon = 'banknote';
                                        if ($item['kind'] === 'Book') $icon = 'book';
                                        if ($item['kind'] === 'Equipment') $icon = 'wrench';
                                        if ($item['kind'] === 'Document') $icon = 'file-text';
                                        
                                        $bg_color = 'var(--surface-1)';
                                        $opacity = '1';
                                        $text_style = '';
                                        if ($item['status'] === 'cleared' || $item['status'] === 'waived') {
                                            $bg_color = 'var(--background)';
                                            $opacity = '0.6';
                                            $text_style = 'text-decoration: line-through; color: var(--muted-foreground);';
                                        }
                                    ?>
                                    <div class="flex items-center justify-between" style="padding: 0.5rem 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border); background: <?php echo $bg_color; ?>; opacity: <?php echo $opacity; ?>; transition: all 0.2s;">
                                        <div class="flex items-start gap-3">
                                            <i data-lucide="<?php echo $icon; ?>" style="width: 14px; height: 14px; color: var(--muted-foreground); margin-right: 0.5rem; margin-top: 0.25rem;"></i>
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2">
                                                    <div class="text-sm font-medium" style="<?php echo $text_style; ?>">
                                                        <?php echo htmlspecialchars($item['title']); ?>
                                                    </div>
                                                    <?php if ($item['status'] === 'cleared'): ?>
                                                        <div style="font-size: 0.65rem; font-weight: 600; color: var(--status-approved); background: var(--status-approved-bg); padding: 0.125rem 0.375rem; border-radius: 9999px;">
                                                            CLEARED
                                                        </div>
                                                    <?php elseif ($item['status'] === 'waived'): ?>
                                                        <div style="font-size: 0.65rem; font-weight: 600; color: var(--status-pending); background: var(--status-pending-bg); padding: 0.125rem 0.375rem; border-radius: 9999px;">
                                                            WAIVED
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($item['amount'] > 0): ?>
                                                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--status-emergency); background: var(--status-emergency-bg); border: 1px solid color-mix(in srgb, var(--status-emergency) 30%, transparent); padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                                            BDT <?php echo number_format($item['amount'], 0); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($item['description']): ?>
                                                    <div class="text-xs text-muted mt-1"><?php echo htmlspecialchars($item['description']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($item['due_date']): ?>
                                                    <div class="text-xs text-muted" style="margin-top: 0.125rem;">Due: <?php echo date('M d, Y', strtotime($item['due_date'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-6" style="margin-right: 0.5rem;">
                                            <?php if ($item['status'] === 'outstanding'): ?>
                                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="toggle_item_status">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="status" value="cleared">
                                                    <button type="submit" class="icon-action-btn icon-action-btn-check" title="Mark Cleared">
                                                        <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                                                    </button>
                                                </form>
                                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="toggle_item_status">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="status" value="waived">
                                                    <button type="submit" class="icon-action-btn icon-action-btn-waive" title="Waive Item">
                                                        <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="toggle_item_status">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="status" value="outstanding">
                                                    <button type="submit" class="icon-action-btn" style="color: var(--muted-foreground);" title="Undo (Mark Outstanding)">
                                                        <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="student-details.php?id=<?php echo $student_id; ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this item?')">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="icon-action-btn icon-action-btn-trash" title="Delete Item">
                                                    <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                                <div class="text-xs text-muted" style="font-style: italic; opacity: 0.7;">No items recorded. Add dues, books or equipment the student must clear.</div>
                            <?php endif; ?>
                            
                            

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Audit Trail -->
                <div class="pt-2">
                    <div class="text-xs text-muted mb-4" style="text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Audit Trail</div>
                    <div class="flex flex-col gap-4" style="border-left: 2px solid var(--border); padding-left: 1rem; margin-left: 0.5rem;">
                        <?php foreach ($audit as $log): ?>
                            <?php 
                                $dot_color = 'var(--primary)';
                                if (strpos($log['action'], 'approved') !== false) $dot_color = 'var(--status-approved)';
                                elseif (strpos($log['action'], 'denied') !== false) $dot_color = 'var(--status-denied)';
                                elseif (strpos($log['action'], 'item') !== false) $dot_color = 'var(--muted-foreground)';
                            ?>
                            <div style="position: relative;">
                                <div style="position: absolute; left: -1.35rem; top: 0.25rem; width: 0.6rem; height: 0.6rem; border-radius: 50%; background: <?php echo $dot_color; ?>; outline: 4px solid var(--card);"></div>
                                <div class="text-sm font-medium">
                                    <?php 
                                        $actionText = ucwords(str_replace('_', ' ', $log['action']));
                                        if ($log['dept_name']) $actionText .= " - " . $log['dept_name'];
                                        echo htmlspecialchars($actionText);
                                    ?>
                                </div>
                                <div class="text-xs text-muted">
                                    <?php echo htmlspecialchars($log['actor_name']); ?> • <?php echo date('M d, h:i A', strtotime($log['created_at'])); ?>
                                </div>
                                <?php if ($log['comments']): ?>
                                    <div class="text-xs text-muted mt-1" style="background: var(--surface-2); padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border);"><?php echo htmlspecialchars($log['comments']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($audit) === 0): ?>
                            <div class="text-sm text-muted">No audit logs available for this application.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>