<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $ds_id = (int)$_POST['ds_id'];
    $message = trim($_POST['message_text'] ?? '');
    if ($message) {
        $stmt = $pdo->prepare("INSERT INTO messages (department_status_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$ds_id, $_SESSION['user_id'], $message]);
        
        // Handle AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $time = date('g:i A');
            echo '<div style="display: flex; flex-direction: column; max-width: 85%; align-self: flex-end;">
                    <div style="padding: 0.75rem 1rem; border-radius: 1rem; font-size: 0.875rem; background: var(--primary); color: white; border-bottom-right-radius: 0.25rem;">' . nl2br(htmlspecialchars($message)) . '</div>
                    <span class="text-xs text-muted mt-1 text-right mr-1">' . $time . '</span>
                  </div>';
            exit();
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch user data
$stmt = $pdo->prepare("SELECT email, course, batch FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch active application
$is_historical_view = false;
if (isset($_GET['view_id'])) {
    $stmt = $pdo->prepare("SELECT id, created_at, overall_status, is_emergency, course, batch FROM applications WHERE user_id = ? AND id = ?");
    $stmt->execute([$_SESSION['user_id'], (int)$_GET['view_id']]);
    $application = $stmt->fetch();
    $is_historical_view = $application !== false;
}

if (!isset($application) || !$application) {
    $stmt = $pdo->prepare("SELECT id, created_at, overall_status, is_emergency, course, batch FROM applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $application = $stmt->fetch();
    $is_historical_view = false;
}

// Fetch previous applications
$previous_applications = [];
if ($application) {
    $stmt = $pdo->prepare("SELECT id, created_at, overall_status, course, batch FROM applications WHERE user_id = ? AND id != ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id'], $application['id']]);
    $previous_applications = $stmt->fetchAll();
}

// Fetch clearance items (dues/fines)
$clearance_items = [];
$outstanding_amount = 0;
$stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$all_items = $stmt->fetchAll();

foreach ($all_items as $item) {
    if ($item['status'] === 'outstanding') {
        $outstanding_amount += $item['amount'];
    }
    $clearance_items[$item['department_id']][] = $item;
}

// Fetch department status if application exists
$departments = [];
$messages_by_ds = [];
$total_depts = 0;
$approved_depts = 0;
$progress_percent = 0;
$target_date = null;

if ($application) {
    $target_date = date('M d, Y', strtotime($application['created_at'] . ' + 20 days'));
    $days_left = max(0, (strtotime($application['created_at'] . ' + 20 days') - time()) / (60 * 60 * 24));
    
    $stmt = $pdo->prepare("
        SELECT ds.id as ds_id, ds.status, ds.comments, ds.unread_student_updates, d.id as dept_id, d.name 
        FROM department_status ds
        JOIN departments d ON ds.department_id = d.id
        WHERE ds.application_id = ?
        ORDER BY d.name ASC
    ");
    $stmt->execute([$application['id']]);
    $departments = $stmt->fetchAll();
    
    $total_depts = count($departments);
    foreach ($departments as $dept) {
        if ($dept['status'] === 'approved') {
            $approved_depts++;
        }
    }
    
    if ($total_depts > 0) {
        $progress_percent = round(($approved_depts / $total_depts) * 100);
    }

    // Fetch messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name, u.role 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        JOIN department_status ds ON m.department_status_id = ds.id
        WHERE ds.application_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$application['id']]);
    $all_messages = $stmt->fetchAll();
    foreach ($all_messages as $msg) {
        $messages_by_ds[$msg['department_status_id']][] = $msg;
    }
}
?>
<?php 
$page_title = 'Dashboard';
$active_tab = 'dashboard';
require_once __DIR__ . '/../includes/header.php'; 
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <?php if ($is_historical_view): ?>
            <div style="margin-bottom: 0.5rem;">
                <a href="dashboard.php" class="text-sm font-medium" style="color: var(--primary); display: inline-flex; align-items: center; gap: 0.25rem;">
                    <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> Back to Active Dashboard
                </a>
            </div>
            <h1 class="card-title text-2xl">Historical Application</h1>
            <p class="text-muted">Viewing details of a past clearance application.</p>
        <?php else: ?>
            <h1 class="card-title text-2xl">Your Clearance</h1>
            <p class="text-muted">Track approvals across departments and download your certificate.</p>
        <?php endif; ?>
    </div>
    <div style="text-align: right;">
        <?php if (!$application || $application['overall_status'] === 'completed'): ?>
            <a href="apply.php" class="btn btn-primary">
                <i data-lucide="plus" style="width: 16px; height: 16px;"></i> New Application
            </a>
        <?php else: ?>
            <div style="display: inline-block; cursor: not-allowed;" title="Complete current application first">
                <button class="btn btn-primary" disabled style="opacity: 0.5; pointer-events: none; background-color: var(--primary);">
                    <i data-lucide="plus" style="width: 16px; height: 16px;"></i> New Application
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($application): ?>
    <?php if ($outstanding_amount > 0): ?>
        <div class="card mb-6" style="background-color: var(--status-emergency-bg); border-color: color-mix(in srgb, var(--status-emergency) 20%, transparent);">
            <div class="card-content" style="padding: 1rem; display: flex; align-items: flex-start; gap: 1rem;">
                <div style="background-color: color-mix(in srgb, var(--status-emergency) 20%, white); color: var(--status-emergency); width: 40px; height: 40px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="wallet"></i>
                </div>
                <div>
                    <h3 style="margin-bottom: 0.25rem; font-weight: 600; color: var(--status-emergency);">You have <?php echo count(array_filter($all_items, fn($i) => $i['status'] === 'outstanding')); ?> pending item(s) to clear</h3>
                    <p class="text-sm" style="color: color-mix(in srgb, var(--status-emergency) 70%, black);">Total amount due: <strong>$<?php echo number_format($outstanding_amount, 2); ?></strong>. Please visit the respective departments to clear your dues.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-6">
        <div class="card-header" style="flex-direction: row; justify-content: space-between; align-items: flex-start;">
            <div>
                <h2 class="card-title mb-2"><?php echo htmlspecialchars(($application['course'] ?? $user['course']) . ' · ' . ($application['batch'] ?? $user['batch'])); ?></h2>
                <p class="text-sm text-muted">Submitted <?php echo date('M d, Y', strtotime($application['created_at'])); ?></p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($application['is_emergency']): ?>
                    <span class="status-badge status-emergency"><i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i> Emergency</span>
                <?php endif; ?>

                <?php if ($application['overall_status'] === 'completed'): ?>
                    <span class="status-badge status-approved"><i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> Completed</span>
                <?php elseif ($application['overall_status'] === 'action_required'): ?>
                    <span class="status-badge status-denied"><i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i> Action Required</span>
                <?php else: ?>
                    <span class="status-badge status-pending"><i data-lucide="clock" style="width: 14px; height: 14px;"></i> In Progress</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-content">
            <div class="flex items-center justify-between text-sm font-medium mb-2">
                <span><?php echo $approved_depts; ?> of <?php echo $total_depts; ?> departments approved</span>
                <span><?php echo $progress_percent; ?>%</span>
            </div>
            <div style="height: 8px; width: 100%; background-color: var(--secondary); border-radius: 9999px; overflow: hidden; margin-bottom: 1.5rem;">
                <div style="height: 100%; background: var(--gradient-primary); width: <?php echo $progress_percent; ?>%; transition: width 0.5s ease-in-out;"></div>
            </div>

            <!-- Target completion removed to match design -->

            <style>
                .dept-card:hover {
                    border-color: var(--primary) !important;
                }
            </style>


            <h3 class="font-semibold text-sm mb-3">Departments</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <?php foreach ($departments as $dept): ?>
                    <div style="position: relative; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem 1rem; display: flex; align-items: center; justify-content: space-between; background-color: var(--surface-1); cursor: pointer; transition: border-color 0.2s;" onclick="
                        if (this.querySelector('.red-dot')) {
                            this.querySelector('.red-dot').style.display = 'none';
                            fetch('/clearpath/api/mark_dept_updates_read.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'ds_id=<?php echo $dept['ds_id']; ?>'
                            });
                        }
                        openModal('modal-<?php echo $dept['ds_id']; ?>');
                    " class="dept-card">
                        <?php if ($dept['unread_student_updates']): ?>
                            <div class="red-dot" style="position: absolute; top: -4px; right: -4px; width: 12px; height: 12px; background-color: var(--destructive); border-radius: 50%; border: 2px solid var(--surface-1);"></div>
                        <?php endif; ?>
                        
                        <div class="flex flex-col gap-1">
                            <?php
                                $dept_items = array_filter($clearance_items[$dept['dept_id']] ?? [], fn($i) => $i['status'] === 'outstanding');
                                $dept_due = array_sum(array_column($dept_items, 'amount'));
                            ?>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm"><?php echo htmlspecialchars($dept['name']); ?></span>
                                <?php if (count($dept_items) > 0): ?>
                                    <span style="background: var(--surface-2); padding: 2px 8px; border-radius: 99px; font-size: 0.7rem; font-weight: 600; color: var(--foreground);"><?php echo count($dept_items); ?> pending</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dept_due > 0): ?>
                                <span class="text-xs" style="color: var(--status-emergency); font-weight: bold;">BDT <?php echo number_format($dept_due); ?> due for <?php echo count($dept_items); ?> item(s)</span>
                            <?php endif; ?>
                            <?php if (!empty($dept['comments'])): ?>
                                <div class="flex items-center gap-1 text-xs text-muted mt-1">
                                    <i data-lucide="flag" style="width: 12px; height: 12px;"></i>
                                    <span><?php echo htmlspecialchars($dept['comments']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if ($dept['status'] === 'approved'): ?>
                                <span class="status-badge status-approved"><i data-lucide="check-circle" style="width: 12px; height: 12px;"></i> Approved</span>
                            <?php elseif ($dept['status'] === 'denied'): ?>
                                <span class="status-badge status-denied"><i data-lucide="x-circle" style="width: 12px; height: 12px;"></i> Denied</span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><i data-lucide="clock" style="width: 12px; height: 12px;"></i> Pending</span>
                            <?php endif; ?>
                            <i data-lucide="chevron-right" style="width: 16px; height: 16px; color: var(--muted-foreground);"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-footer justify-between">
            <?php if ($application['overall_status'] === 'completed'): ?>
                <div class="flex items-center gap-2" style="color: var(--status-approved);">
                    <span class="text-sm font-medium">Clearance completed</span>
                </div>
                <a href="certificate.php?id=<?php echo $application['id']; ?>" class="btn btn-primary">
                    <i data-lucide="download" style="width: 16px; height: 16px;"></i> Download Certificate
                </a>
            <?php else: ?>
                <span class="text-sm text-muted">Certificate unlocks once all departments approve.</span>
                <button class="btn btn-outline" disabled style="opacity: 0.5;">
                    <i data-lucide="download" style="width: 16px; height: 16px;"></i> Download Certificate
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (count($previous_applications) > 0 && !$is_historical_view): ?>
        <div class="mt-6">
            <h3 class="text-sm font-medium text-muted mb-4">Previous applications</h3>
            <?php foreach ($previous_applications as $prev): ?>
                <a href="dashboard.php?view_id=<?php echo $prev['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                    <div class="card mb-2" style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; transition: border-color 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                        <div>
                            <div class="font-medium text-sm"><?php echo htmlspecialchars(($prev['course'] ?? $user['course']) . ' · ' . ($prev['batch'] ?? $user['batch'])); ?></div>
                            <div class="text-xs text-muted"><?php echo date('M d, Y', strtotime($prev['created_at'])); ?></div>
                        </div>
                        <?php if ($prev['overall_status'] === 'completed'): ?>
                            <span class="status-badge status-approved"><i data-lucide="check-circle" style="width: 12px; height: 12px;"></i> Completed</span>
                        <?php elseif ($prev['overall_status'] === 'action_required'): ?>
                            <span class="status-badge status-denied"><i data-lucide="alert-circle" style="width: 12px; height: 12px;"></i> Action Required</span>
                        <?php else: ?>
                            <span class="status-badge status-pending"><i data-lucide="clock" style="width: 12px; height: 12px;"></i> In Progress</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Empty State -->
    <div class="card" style="text-align: center; padding: 5rem 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px dashed var(--border); background-color: transparent; box-shadow: none;">
        <div style="background-color: color-mix(in srgb, var(--primary) 15%, transparent); color: var(--primary); width: 64px; height: 64px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
            <i data-lucide="file-text" style="width: 28px; height: 28px; stroke-width: 2px;"></i>
        </div>
        <h2 class="card-title mb-2" style="font-size: 1.25rem;">No applications yet</h2>
        <p class="card-description mb-6" style="max-width: 400px; line-height: 1.5;">Start your clearance process by submitting a new application — we'll guide you through every department.</p>
        <a href="apply.php" class="btn btn-primary" style="padding: 0 1.25rem;">
            <i data-lucide="plus" style="width: 16px; height: 16px;"></i> Start Application
        </a>
    </div>
<?php endif; ?>

<?php if ($application): ?>
    <?php foreach ($departments as $dept): ?>
        <?php 
            $dept_items = $clearance_items[$dept['dept_id']] ?? []; 
            $dept_messages = $messages_by_ds[$dept['ds_id']] ?? [];
        ?>
        <div id="modal-<?php echo $dept['ds_id']; ?>" style="display: none; position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 1rem;">
            <div style="background: var(--surface-1); width: 100%; max-width: 650px; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); overflow: hidden;">
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($dept['name']); ?></h3>
                        <p class="text-sm text-muted">Clearance details</p>
                    </div>
                    <button type="button" onclick="closeModal('modal-<?php echo $dept['ds_id']; ?>')" style="border: none; background: transparent; cursor: pointer; padding: 0.25rem; display: flex; align-items: center; justify-content: center; color: var(--muted-foreground);">
                        <i data-lucide="x" style="width: 20px; height: 20px;"></i>
                    </button>
                </div>
                
                <div style="padding: 1.5rem;">
                    <h4 class="font-medium text-sm mb-2">Pending Requirements</h4>
                    <?php if (count($dept_items) === 0): ?>
                        <p class="text-sm text-muted italic mb-6">Nothing pending from this department.</p>
                    <?php else: ?>
                        <div class="flex-col gap-2 mb-6">
                            <?php foreach ($dept_items as $item): ?>
                                <?php
                                    $bg_color = 'var(--surface-2)';
                                    $opacity = '1';
                                    $text_style = '';
                                    if ($item['status'] === 'cleared' || $item['status'] === 'waived') {
                                        $bg_color = 'var(--background)';
                                        $opacity = '0.6';
                                        $text_style = 'text-decoration: line-through; color: var(--muted-foreground);';
                                    }
                                ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius-md); background: <?php echo $bg_color; ?>; opacity: <?php echo $opacity; ?>;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span class="text-sm font-medium" style="<?php echo $text_style; ?>"><?php echo htmlspecialchars($item['title']); ?></span>
                                        <?php if ($item['status'] === 'cleared'): ?>
                                            <span style="font-size: 0.65rem; font-weight: 600; color: var(--status-approved); background: var(--status-approved-bg); padding: 0.125rem 0.375rem; border-radius: 9999px;">CLEARED</span>
                                        <?php elseif ($item['status'] === 'waived'): ?>
                                            <span style="font-size: 0.65rem; font-weight: 600; color: var(--status-pending); background: var(--status-pending-bg); padding: 0.125rem 0.375rem; border-radius: 9999px;">WAIVED</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-sm" style="color: var(--status-emergency); font-weight: 600;">$<?php echo number_format($item['amount'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <h4 class="font-medium text-sm mb-2" style="display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="message-square" style="width: 14px; height: 14px;"></i> Messages</h4>
                    <div id="chat-<?php echo $dept['ds_id']; ?>" style="background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem; display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem; max-height: 300px; overflow-y: auto;">
                        <?php if (count($dept_messages) === 0): ?>
                            <p class="text-sm text-muted text-center italic py-4">No messages yet — start the conversation.</p>
                        <?php else: ?>
                            <?php foreach ($dept_messages as $msg): ?>
                                <?php $is_me = $msg['sender_id'] == $_SESSION['user_id']; ?>
                                <div style="display: flex; flex-direction: column; max-width: 85%; <?php echo $is_me ? 'align-self: flex-end;' : 'align-self: flex-start;'; ?>">
                                    <?php if (!$is_me): ?>
                                        <span class="text-xs text-muted mb-1 ml-1"><?php echo htmlspecialchars($msg['full_name']); ?></span>
                                    <?php endif; ?>
                                    <div style="padding: 0.75rem 1rem; border-radius: 1rem; font-size: 0.875rem; <?php echo $is_me ? 'background: var(--primary); color: white; border-bottom-right-radius: 0.25rem;' : 'background-color: var(--surface-1); border: 1px solid var(--border); border-bottom-left-radius: 0.25rem;'; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>
                                    <span class="text-xs text-muted mt-1 <?php echo $is_me ? 'text-right mr-1' : 'ml-1'; ?>"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Message Input -->
                    <form onsubmit="submitStudentMessage(event, this, 'chat-<?php echo $dept['ds_id']; ?>')" style="display: flex; gap: 0.75rem; border: 2px solid var(--primary); border-radius: 9999px; padding: 0.5rem 0.5rem 0.5rem 1.25rem; align-items: center; background: var(--surface-1);">
                        <input type="hidden" name="ds_id" value="<?php echo $dept['ds_id']; ?>">
                        <input type="text" name="message_text" placeholder="Write a message..." required style="flex: 1; border: none; outline: none; background: transparent; font-size: 0.95rem; padding: 0.25rem 0;">
                        <button type="submit" name="action" value="send_message" class="btn btn-primary" style="border-radius: 50%; width: 2.5rem; height: 2.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="send" style="width: 16px; height: 16px; margin: 0; position: relative; right: 1px;"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
        // Scroll chat to bottom when modal opens
        const chatBox = document.querySelector('#' + id + ' [id^="chat-"]');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }
    
    function submitStudentMessage(event, form, chatBoxId) {
        event.preventDefault();
        const formData = new FormData(form);
        formData.append('action', 'send_message');
        
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.style.opacity = '0.7';

        fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            const chatBox = document.getElementById(chatBoxId);
            const noMessages = chatBox.querySelector('p.italic');
            if (noMessages) noMessages.remove();
            
            chatBox.insertAdjacentHTML('beforeend', html);
            form.reset();
            chatBox.scrollTop = chatBox.scrollHeight;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .finally(() => {
            btn.disabled = false;
            btn.style.opacity = '1';
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>