<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';

if (!empty($_SESSION['user_id'])) {
    logActivity((int)$_SESSION['user_id'], null, 'LOGOUT', 'User logged out.');
}

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: '.appPath('login.php'));
exit;
