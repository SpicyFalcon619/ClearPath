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
    </style>
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
                            <a href="/clearpath/admin/students.php" class="tab-item <?php echo $active === 'users' ? 'active' : ''; ?>">
                                <i data-lucide="users" style="width: 14px; height: 14px;"></i> Users
                            </a>
                            <a href="/clearpath/admin/departments.php" class="tab-item <?php echo $active === 'departments' ? 'active' : ''; ?>">
                                <i data-lucide="building-2" style="width: 14px; height: 14px;"></i> Departments
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div class="header-right">
                    <?php if ($role === 'student'): ?>
                        <a href="/clearpath/student/notifications.php" class="icon-btn" title="Notifications">
                            <i data-lucide="bell" style="width: 18px; height: 18px;"></i>
                        </a>
                    <?php endif; ?>
                    <span class="user-email"><?php echo htmlspecialchars(current_user_email()); ?></span>
                    <a href="/clearpath/auth/logout.php" class="icon-btn" title="Sign out">
                        <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                    </a>
                </div>
            </div>
        </header>
        <main class="main-content">
