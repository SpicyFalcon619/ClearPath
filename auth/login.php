<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';

if (is_logged_in()) {
    require_role(current_user_role());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        // Find the user by their email
        $stmt = $pdo->prepare("SELECT id, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify the typed password matches the hashed password in the database
        if ($user && password_verify($password, $user['password'])) {
            // Login successful! Save user info in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect them to the correct dashboard based on their role
            if ($user['role'] === 'student') {
                header("Location: /clearpath/student/dashboard.php");
            } elseif ($user['role'] === 'dept_admin') {
                header("Location: /clearpath/dept/dashboard.php");
            } elseif ($user['role'] === 'master_admin') {
                header("Location: /clearpath/admin/dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClearPath - Login</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .auth-container {
            min-height: 100vh;
            background-color: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 450px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }
        .brand-icon {
            width: 2rem;
            height: 2rem;
            border-radius: var(--radius-md);
            background-color: var(--primary);
            color: var(--primary-foreground);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tabs-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background-color: var(--secondary);
            border-radius: var(--radius-md);
            padding: 0.25rem;
            margin-bottom: 1.5rem;
        }
        .tab-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            color: var(--muted-foreground);
            transition: all 0.2s;
            text-decoration: none;
        }
        .tab-trigger.active {
            background-color: var(--card);
            color: var(--foreground);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .error-message {
            color: var(--destructive);
            background-color: var(--destructive-foreground);
            border: 1px solid var(--destructive);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .auth-footer {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-wrapper">
            <a href="/" class="brand-logo">
                <div class="brand-icon">
                    <i data-lucide="graduation-cap" style="width: 16px; height: 16px;"></i>
                </div>
                ClearPath
            </a>
            
            <div class="card shadow-md">
                <div class="card-header">
                    <h2 class="card-title">Welcome</h2>
                    <p class="card-description">Sign in to manage your university clearance</p>
                </div>
                <div class="card-content">
                    <div class="tabs-list">
                        <a href="login.php" class="tab-trigger active">Sign in</a>
                        <a href="signup.php" class="tab-trigger">Sign up</a>
                    </div>

                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="label" for="email">Email</label>
                            <input class="input" type="email" name="email" id="email" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="label" for="password">Password</label>
                            <input class="input" type="password" name="password" id="password" required>
                        </div>
                        <button type="submit" class="btn w-full btn-primary">Sign in</button>
                    </form>
                    
                    <p class="auth-footer">
                        New accounts start as Students. Admin roles are assigned by Master Admin.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>