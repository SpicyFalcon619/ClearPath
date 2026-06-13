<?php
require_once __DIR__ . '/auth.php';
// Ensure user is logged in
if (!is_logged_in()) {
    header("Location: /clearpath/auth/login.php");
    exit();
}

function current_user_email() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return '';
    static $email = null;
    if ($email === null) {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $email = $stmt->fetchColumn() ?: '';
    }
    return $email;
}

$unread_notifications_count = 0;
$notifications = [];
if (isset($_SESSION['user_id'])) {
    // Fetch unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications_count = $stmt->fetchColumn();

    // Fetch latest 5 notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClearPath - <?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></title>
    <link rel="stylesheet" href="/clearpath/assets/css/global.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .app-shell {
            min-height: 100vh;
            background-color: var(--surface-2);
            display: flex;
            flex-direction: column;
        }
        .header {
            position: sticky;
            top: 0;
            z-index: 40;
            background-color: var(--surface-1);
            border-bottom: 1px solid var(--border);
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            height: 4rem;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .brand-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--foreground);
            text-decoration: none;
        }
        .brand-icon-small {
            width: 2rem;
            height: 2rem;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, hsl(22 95% 53%), hsl(28 100% 62%));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .user-email {
            font-size: 0.875rem;
            color: var(--muted-foreground);
            margin: 0 0.5rem;
        }
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: var(--radius-md);
            color: var(--muted-foreground);
            text-decoration: none;
            transition: all 0.2s;
        }
        .icon-btn:hover {
            background-color: var(--primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        .main-content {
            flex: 1;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Notifications Dropdown */
        .notif-dropdown-wrapper {
            position: relative;
        }
        .notif-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: var(--destructive);
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            width: 14px;
            height: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 2px solid var(--surface-1);
        }
        .notif-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            width: 320px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 50;
        }
        .notif-dropdown.show {
            display: flex;
        }
        .notif-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            display: block;
            text-decoration: none;
            color: var(--foreground);
            transition: background 0.2s;
        }
        .notif-item:hover {
            background: var(--surface-2);
        }
        .notif-item.unread {
            background: color-mix(in srgb, var(--primary) 5%, transparent);
        }
        .notif-time {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            margin-top: 0.25rem;
        }
    </style>
    <script>
        function toggleNotifications(e) {
            e.preventDefault();
            const dropdown = document.getElementById('notif-dropdown');
            dropdown.classList.toggle('show');
            
            // Mark all as read
            if (dropdown.classList.contains('show')) {
                fetch('/clearpath/api/mark_notifications_read.php', { method: 'POST' })
                    .then(() => {
                        const badge = document.getElementById('notif-badge');
                        if (badge) badge.style.display = 'none';
                        document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                    });
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.notif-dropdown-wrapper');
            const dropdown = document.getElementById('notif-dropdown');
            if (dropdown && dropdown.classList.contains('show') && !wrapper.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</head>
<body>
    <div class="app-shell">
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <a href="/clearpath/" class="brand-link">
                        <div class="brand-icon-small">
                            <i data-lucide="graduation-cap" style="width: 16px; height: 16px;"></i>
                        </div>
                        <span style="background: linear-gradient(135deg, hsl(22 95% 53%), hsl(28 100% 62%)); -webkit-background-clip: text; color: transparent;">ClearPath</span>
                    </a>
                    
                    <nav class="tab-bar">
                        <?php 
                        $role = current_user_role();
                        $active = $active_tab ?? 'dashboard';
                        if ($role === 'student'): 
                        ?>
                            <a href="/clearpath/student/dashboard.php" class="tab-item <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
                                <i data-lucide="layout-dashboard" style="width: 14px; height: 14px;"></i> Dashboard
                            </a>
                            <a href="/clearpath/student/apply.php" class="tab-item <?php echo $active === 'apply' ? 'active' : ''; ?>">
                                <i data-lucide="file-text" style="width: 14px; height: 14px;"></i> New Application
                            </a>
                        <?php elseif ($role === 'dept_admin'): ?>
                            <a href="/clearpath/dept/dashboard.php" class="tab-item <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
                                <i data-lucide="layout-dashboard" style="width: 14px; height: 14px;"></i> Queue
                            </a>
                        <?php elseif ($role === 'master_admin'): ?>
                            <a href="/clearpath/admin/dashboard.php" class="tab-item <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
                                <i data-lucide="layout-dashboard" style="width: 14px; height: 14px;"></i> Overview
                            </a>
                            <a href="/clearpath/admin/users.php" class="tab-item <?php echo $active === 'users' ? 'active' : ''; ?>">
                                <i data-lucide="users" style="width: 14px; height: 14px;"></i> Users
                            </a>
                            <a href="/clearpath/admin/departments.php" class="tab-item <?php echo $active === 'departments' ? 'active' : ''; ?>">
                                <i data-lucide="building-2" style="width: 14px; height: 14px;"></i> Departments
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div class="header-right">
                    <div class="notif-dropdown-wrapper">
                        <a href="#" class="icon-btn" title="Notifications" onclick="toggleNotifications(event)">
                            <i data-lucide="bell" style="width: 18px; height: 18px;"></i>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="notif-badge" id="notif-badge"><?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="notif-dropdown" id="notif-dropdown">
                            <div class="notif-header">
                                <span>Notifications</span>
                            </div>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($notifications)): ?>
                                    <div style="padding: 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; color: var(--muted-foreground); font-size: 0.875rem;">
                                        <i data-lucide="bell-off" style="width: 24px; height: 24px; opacity: 0.5;"></i>
                                        <span>No notifications yet.</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <a href="<?php echo htmlspecialchars($notif['link'] ?: '#'); ?>" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                            <div class="text-sm"><?php echo htmlspecialchars($notif['message']); ?></div>
                                            <div class="notif-time"><?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <a href="/clearpath/profile.php" class="icon-btn" title="Profile">
                        <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                    </a>
                    <a href="/clearpath/auth/logout.php" class="icon-btn" title="Sign out">
                        <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                    </a>
                </div>
            </div>
        </header>
        <main class="main-content">
