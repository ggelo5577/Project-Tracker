<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: '.appPath('index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== csrfToken()) {
        $error = 'Invalid request.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, password_hash, full_name, role, is_active FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $username;
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['last_regenerated'] = time();

                logActivity((int)$user['id'], null, 'LOGIN', 'User logged in.');
                header('Location: '.appPath('index.php'));
                exit;
            } else {
                $error = 'Invalid credentials or account is inactive.';
            }
        }
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DOST PPMIS</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #0F4C81; --accent: #E87722;
            --bg: #F5F7FA;
        }
        body {
            background: linear-gradient(135deg, #0F4C81 0%, #1D6FB5 50%, #0a3560 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px 36px;
            width: 100%; max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        .login-logo {
            width: 64px; height: 64px;
            background: var(--accent);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; color: #fff;
            margin: 0 auto 16px;
        }
        .login-title { text-align: center; font-size: 22px; font-weight: 800; color: var(--primary); margin-bottom: 4px; }
        .login-sub   { text-align: center; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 28px; }
        .form-label  { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.6px; }
        .form-control {
            border: 2px solid #dde3ea;
            border-radius: 10px; padding: 10px 14px; font-size: 14px;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(232,119,34,0.15); }
        .btn-login {
            width: 100%; background: var(--accent); color: #fff;
            border: none; border-radius: 10px; padding: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-login:hover { background: #d4641a; transform: translateY(-1px); }
        .dost-footer {
            text-align: center; margin-top: 20px;
            font-size: 11px; color: #aaa;
        }
        .dost-footer strong { color: var(--primary); }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo"><i class="bi bi-diagram-3-fill"></i></div>
    <div class="login-title">PPMIS</div>
    <div class="login-sub">DOST Region VIII · Maasin City</div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="border-radius:10px;font-size:13px;">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= h(appPath('login.php')) ?>" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control"
                   value="<?= h($_POST['username'] ?? '') ?>"
                   placeholder="Enter your username" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="passwordInput" class="form-control"
                       placeholder="Enter your password" required>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="togglePw()" style="border-radius:0 10px 10px 0;border-left:none;">
                    <i class="bi bi-eye" id="pwIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>

    <div class="dost-footer">
        <strong>Department of Science and Technology</strong><br>
        Project Progress & Management Information System
    </div>
</div>

<script>
function togglePw() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('pwIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
