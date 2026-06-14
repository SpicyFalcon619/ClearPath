<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('dept_admin');

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$app_id = (int)$_GET['id'];

// Fetch user and department
$stmt = $pdo->prepare("
    SELECT u.id as admin_id, d.id as dept_id, d.name as dept_name
    FROM users u JOIN departments d ON u.department_id = d.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Fetch application and status
$stmt = $pdo->prepare("
    SELECT 
        a.id, a.reason, a.is_emergency, a.emergency_justification, a.created_at,
        u.id as student_user_id, u.full_name, u.email, u.course, u.batch,
        ds.id as ds_id, ds.status, ds.comments
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN department_status ds ON ds.application_id = a.id
    WHERE a.id = ? AND ds.department_id = ?
");
$stmt->execute([$app_id, $admin['dept_id']]);
$app = $stmt->fetch();

if (!$app) {
    die("Application not found or not assigned to your department.");
}

// Fetch items
$stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE application_id = ? AND department_id = ? ORDER BY created_at ASC");
$stmt->execute([$app_id, $admin['dept_id']]);
$items = $stmt->fetchAll();

// Outstanding for logic checks
$outstanding_items = array_filter($items, fn($i) => $i['status'] === 'outstanding');

// Fetch documents
$stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ?");
$stmt->execute([$app_id]);
$documents = $stmt->fetchAll();

$success_msg = '';
$error_msg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'approve' || $action === 'deny' || $action === 'pending') {
            $new_status = $action;
            if ($action === 'approve') $new_status = 'approved';
            if ($action === 'deny') $new_status = 'denied';
            
            if ($new_status === 'approved' && count($outstanding_items) > 0) {
                $error_msg = "Cannot approve. Student still has outstanding dues.";
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE department_status SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_status, $admin['admin_id'], $app['ds_id']]);
                    
                    $stmt = $pdo->prepare("INSERT INTO audit_log (application_id, department_id, actor_id, action) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$app_id, $admin['dept_id'], $admin['admin_id'], $new_status]);
                    
                    // Evaluate overall
                    $stmt = $pdo->prepare("SELECT status FROM department_status WHERE application_id = ?");
                    $stmt->execute([$app_id]);
                    $all_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $overall = 'completed';
                    foreach ($all_statuses as $st) {
                        if ($st === 'denied') { $overall = 'action_required'; break; }
                        elseif ($st === 'pending') { $overall = 'in_progress'; }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE applications SET overall_status = ? WHERE id = ?");
                    $stmt->execute([$overall, $app_id]);
                    
                    $pdo->commit();
                    echo "<script>window.location.reload();</script>";
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Database error: " . $e->getMessage();
                }
            }
        } elseif ($action === 'add_item') {
            $due_kind = trim($_POST['kind'] ?? 'fee');
            $due_title = trim($_POST['title'] ?? '');
            $due_amount = floatval($_POST['amount'] ?? 0);
            $due_date = trim($_POST['due_date'] ?? '');
            $due_notes = trim($_POST['description'] ?? '');
            
            if ($due_date === '') $due_date = null;
            if ($due_notes === '') $due_notes = null;

            if ($due_title) {
                $stmt = $pdo->prepare("INSERT INTO clearance_items (user_id, department_id, application_id, title, amount, kind, due_date, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'outstanding')");
                if ($stmt->execute([$app['student_user_id'], $admin['dept_id'], $app_id, $due_title, $due_amount, $due_kind, $due_date, $due_notes])) {
                    $success_msg = "Item added successfully.";
                    
                    if ($app['status'] === 'approved') {
                        $stmt = $pdo->prepare("UPDATE department_status SET status = 'pending' WHERE id = ?");
                        $stmt->execute([$app['ds_id']]);
                        $app['status'] = 'pending';
                    }
                    
                    $stmt = $pdo->prepare("UPDATE department_status SET unread_student_updates = 1 WHERE id = ?");
                    $stmt->execute([$app['ds_id']]);
                    
                    $msg = "New clearance item added: {$due_title}";
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, '/clearpath/student/dashboard.php')");
                    $stmt->execute([$app['student_user_id'], $msg]);
                    
                    // Refresh items
                    $stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE application_id = ? AND department_id = ? ORDER BY created_at ASC");
                    $stmt->execute([$app_id, $admin['dept_id']]);
                    $items = $stmt->fetchAll();
                    $outstanding_items = array_filter($items, fn($i) => $i['status'] === 'outstanding');
                }
            }
        } elseif ($action === 'toggle_item_status') {
            $item_id = (int)$_POST['item_id'];
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE clearance_items SET status = ? WHERE id = ? AND department_id = ?");
            if ($stmt->execute([$status, $item_id, $admin['dept_id']])) {
                $stmt = $pdo->prepare("UPDATE department_status SET unread_student_updates = 1 WHERE id = ?");
                $stmt->execute([$app['ds_id']]);
                
                $msg = "Item status updated to " . strtoupper($status);
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, '/clearpath/student/dashboard.php')");
                $stmt->execute([$app['student_user_id'], $msg]);
                // Refresh items
                $stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE application_id = ? AND department_id = ? ORDER BY created_at ASC");
                $stmt->execute([$app_id, $admin['dept_id']]);
                $items = $stmt->fetchAll();
                $outstanding_items = array_filter($items, fn($i) => $i['status'] === 'outstanding');
            }
        } elseif ($action === 'delete_item') {
            $item_id = (int)$_POST['item_id'];
            $stmt = $pdo->prepare("DELETE FROM clearance_items WHERE id = ? AND department_id = ?");
            if ($stmt->execute([$item_id, $admin['dept_id']])) {
                $success_msg = "Item deleted.";
                // Refresh items
                $stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE application_id = ? AND department_id = ? ORDER BY created_at ASC");
                $stmt->execute([$app_id, $admin['dept_id']]);
                $items = $stmt->fetchAll();
                $outstanding_items = array_filter($items, fn($i) => $i['status'] === 'outstanding');
            }
        } elseif ($action === 'send_message') {
            $message_text = trim($_POST['message_text'] ?? '');
            if ($message_text) {
                $stmt = $pdo->prepare("INSERT INTO messages (department_status_id, sender_id, message) VALUES (?, ?, ?)");
                if ($stmt->execute([$app['ds_id'], $admin['admin_id'], $message_text])) {
                    $stmt = $pdo->prepare("UPDATE department_status SET unread_student_updates = 1 WHERE id = ?");
                    $stmt->execute([$app['ds_id']]);
                    
                    $msg = "New message from department";
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, '/clearpath/student/dashboard.php')");
                    $stmt->execute([$app['student_user_id'], $msg]);
                }
            }
        }
    }
}

// Fetch messages
$stmt = $pdo->prepare("
    SELECT m.*, u.full_name, u.role 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.department_status_id = ? 
    ORDER BY m.created_at ASC
");
$stmt->execute([$app['ds_id']]);
$messages = $stmt->fetchAll();
?>
<div class="flex-col gap-4" style="width: 100%;">
    <div class="flex items-start justify-between mb-4 border-b border-border" style="padding-bottom: 1rem;">
        <div>
            <h2 class="text-lg font-semibold mb-1"><?php echo htmlspecialchars($app['full_name']); ?></h2>
            <div class="text-xs text-muted">
                <?php echo htmlspecialchars($app['email']); ?> <span style="margin: 0 4px;">·</span> <?php echo htmlspecialchars($app['course'] . ' · ' . $app['batch']); ?>
            </div>
        </div>
        <button type="button" class="btn btn-ghost" style="padding: 0.5rem; margin-right: -0.5rem; margin-top: -0.25rem;" onclick="closeReviewModal()">
            <i data-lucide="x" style="width: 20px; height: 20px;"></i>
        </button>
    </div>

    <?php if ($error_msg): ?>
        <div style="color: var(--destructive); background-color: var(--destructive-foreground); border: 1px solid var(--destructive); padding: 0.75rem; border-radius: var(--radius-md); font-size: 0.875rem; text-align: center; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>


    <div class="flex-col gap-6">
            
            <!-- Documents -->
            <div class="text-xs font-semibold text-muted mb-2" style="letter-spacing: 0.05em; text-transform: uppercase;">DOCUMENTS</div>
            <?php if (count($documents) === 0): ?>
                <div class="text-sm text-muted mb-6" style="padding: 0.75rem; border: 1px dashed var(--border); border-radius: var(--radius-md); text-align: center;">No documents uploaded.</div>
            <?php else: ?>
                <div class="flex gap-4 flex-wrap mb-6">
                    <?php foreach ($documents as $doc): ?>
                        <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" style="color: var(--primary); display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.875rem;">
                            <i data-lucide="download" style="width: 14px; height: 14px;"></i> <?php echo htmlspecialchars($doc['file_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Dues Header -->
            <div class="flex items-center justify-between mt-8 mb-4">
                <div>
                    <div class="text-sm font-medium">Dues & obligations for this student</div>
                    <?php
                        $total_due = 0;
                        foreach($items as $i) { if($i['status'] === 'outstanding') $total_due += $i['amount']; }
                    ?>
                    <div class="text-xs text-muted" style="margin-top: 0.125rem;">
                        <?php echo count($items); ?> items <?php if($total_due > 0) echo '· BDT ' . number_format($total_due, 0) . ' due'; ?>
                    </div>
                </div>
                <button type="button" class="btn btn-outline" style="height: 1.75rem; padding: 0 0.75rem; font-size: 0.75rem; border-radius: 9999px; border-color: var(--border);" onclick="let f = document.getElementById('add-due-form'); f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';">
                    <i data-lucide="plus" style="width: 14px; height: 14px; margin-right: 0.25rem;"></i> Add item
                </button>
            </div>

            <!-- Add Item Form -->
            <div id="add-due-form" style="display: none; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" class="flex flex-col gap-4" onsubmit="submitReviewModal(event, this)">
                    <input type="hidden" name="action" value="add_item">
                    
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
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-due-form').style.display='none'">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>

            <div class="flex flex-col gap-2">
                <?php if (count($items) === 0): ?>
                    <div class="text-xs text-muted" style="font-style: italic; opacity: 0.7;">
                        No items recorded. Add dues, books or equipment the student must clear.
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $icon = 'circle-dot';
                            if ($item['kind'] === 'fee') $icon = 'banknote';
                            if ($item['kind'] === 'book') $icon = 'book';
                            if ($item['kind'] === 'equipment') $icon = 'wrench';
                            if ($item['kind'] === 'document') $icon = 'file-text';
                            
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
                                            <div style="font-size: 0.65rem; font-weight: 600; color: var(--status-approved); background: var(--status-approved-bg); padding: 0.125rem 0.375rem; border-radius: 9999px;">CLEARED</div>
                                        <?php elseif ($item['status'] === 'waived'): ?>
                                            <div style="font-size: 0.65rem; font-weight: 600; color: var(--status-pending); background: var(--status-pending-bg); padding: 0.125rem 0.375rem; border-radius: 9999px;">WAIVED</div>
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
                                    <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" style="margin: 0;" onsubmit="submitReviewModal(event, this)">
                                        <input type="hidden" name="action" value="toggle_item_status">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="status" value="cleared">
                                        <button type="submit" class="icon-action-btn icon-action-btn-check" title="Mark Cleared">
                                            <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                                        </button>
                                    </form>
                                    <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" style="margin: 0;" onsubmit="submitReviewModal(event, this)">
                                        <input type="hidden" name="action" value="toggle_item_status">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="status" value="waived">
                                        <button type="submit" class="icon-action-btn icon-action-btn-waive" title="Waive Item">
                                            <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" style="margin: 0;" onsubmit="submitReviewModal(event, this)">
                                        <input type="hidden" name="action" value="toggle_item_status">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="status" value="outstanding">
                                        <button type="submit" class="icon-action-btn" style="color: var(--muted-foreground);" title="Undo (Mark Outstanding)">
                                            <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" style="margin: 0;" onsubmit="if(confirm('Delete this item?')) { submitReviewModal(event, this); } else { event.preventDefault(); }">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="icon-action-btn icon-action-btn-trash" title="Delete Item">
                                        <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

            <!-- Messages Header -->
            <div class="text-sm font-medium mb-3 flex items-center gap-2" style="margin-top: 2.5rem;">
                <i data-lucide="message-square" style="width: 16px; height: 16px;"></i> Messages with student
            </div>
            
            <div id="chat-box" style="border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface-1); padding: 1rem; margin-bottom: 1rem; max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php if (count($messages) === 0): ?>
                        <div class="text-center text-sm text-muted py-4">No messages yet. Send a message to the student if you need more information.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php $is_admin = $msg['role'] !== 'student'; ?>
                            <div style="display: flex; flex-direction: column; max-width: 85%; <?php echo $is_admin ? 'align-self: flex-end;' : 'align-self: flex-start;'; ?>">
                                <?php if (!$is_admin): ?>
                                    <span class="text-xs text-muted mb-1 ml-1"><?php echo htmlspecialchars($msg['full_name']); ?></span>
                                <?php endif; ?>
                            <div style="padding: 0.5rem 0.75rem; border-radius: 0.75rem; font-size: 0.8rem; <?php echo $is_admin ? 'background: var(--primary); color: white; border-bottom-right-radius: 0.25rem;' : 'background-color: white; border: 1px solid var(--border); border-bottom-left-radius: 0.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'; ?>">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    <div style="font-size: 0.65rem; margin-top: 0.5rem; <?php echo $is_admin ? 'color: rgba(255,255,255,0.8);' : 'color: var(--muted-foreground);'; ?>">
                                        <?php 
                                            $diff = time() - strtotime($msg['created_at']);
                                            if ($diff < 60) echo 'just now';
                                            elseif ($diff < 3600) echo floor($diff/60) . ' minutes ago';
                                            elseif ($diff < 86400) echo 'about ' . floor($diff/3600) . ' hours ago';
                                            else echo date('M d, g:i A', strtotime($msg['created_at']));
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
            </div> <!-- end of chat box -->

            <!-- Message Input -->
            <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" class="flex items-center gap-2 mb-4" onsubmit="submitReviewModal(event, this)">
                <div style="flex: 1; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.5rem; background: white; display: flex; align-items: center;">
                    <input type="text" name="message_text" placeholder="Write a message..." required style="flex: 1; border: none; outline: none; background: transparent; padding: 0.25rem; font-size: 0.8rem;" oninput="const btn = this.closest('form').querySelector('button[type=submit]'); if(this.value.trim() !== '') { btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; } else { btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed'; }">
                </div>
                <button type="submit" name="action" value="send_message" class="btn btn-primary" disabled style="border-radius: var(--radius-md); width: 2.75rem; height: 2.75rem; padding: 0; display: flex; align-items: center; justify-content: center; opacity: 0.5; cursor: not-allowed; transition: all 0.2s;">
                    <i data-lucide="send" style="width: 16px; height: 16px; margin: 0;"></i>
                </button>
            </form>

            <!-- Final Actions Form -->
            <form action="review_modal_content.php?id=<?php echo $app_id; ?>" method="POST" onsubmit="submitReviewModal(event, this)">
                <div class="flex items-center justify-end pt-2" style="gap: 1rem;">
                    <?php if ($app['status'] !== 'pending'): ?>
                        <button type="submit" name="action" value="pending" class="btn btn-ghost" style="border-radius: var(--radius-md); padding: 0.5rem 1rem; color: var(--muted-foreground);">
                            <i data-lucide="clock" style="width: 16px; height: 16px;"></i> Mark Pending
                        </button>
                    <?php endif; ?>
                    <?php if ($app['status'] !== 'denied'): ?>
                        <button type="submit" name="action" value="deny" class="btn btn-outline" style="border-radius: var(--radius-md); padding: 0.5rem 1rem; color: var(--foreground); border-color: var(--border);">
                            <i data-lucide="x-circle" style="width: 16px; height: 16px;"></i> Deny
                        </button>
                    <?php endif; ?>
                    <?php if ($app['status'] !== 'approved'): ?>
                        <button type="submit" name="action" value="approve" class="btn btn-primary" style="border-radius: var(--radius-md); padding: 0.5rem 1.25rem; <?php echo count($outstanding_items) > 0 ? 'opacity: 0.5;' : ''; ?>" <?php echo count($outstanding_items) > 0 ? 'disabled' : ''; ?>>
                            <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> Approve
                        </button>
                    <?php endif; ?>
                </div>
            </form>
</div>

<script>
    // Scroll chat to bottom
    var chatBox = document.getElementById('chat-box');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
</script>

