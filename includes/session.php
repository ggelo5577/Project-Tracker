<?php
// includes/session.php - Session Management & Security Helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode'  => true,
    ]);
}

// Regenerate session ID every 30 minutes to prevent fixation
if (!isset($_SESSION['last_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
} elseif (time() - $_SESSION['last_regenerated'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: '.appPath('login.php'));
        exit;
    }
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id'] ?? 0,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? 'staff',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// CSRF Token
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token mismatch.']));
    }
}

// XSS Prevention
function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Sanitize string input
function sanitize(string $input): string {
    return trim(strip_tags($input));
}

function appPath(string $path = ''): string {
    return rtrim(APP_BASE, '/') . '/' . ltrim($path, '/');
}

function assetPath(string $path = ''): string {
    return appPath('assets/' . ltrim($path, '/'));
}

function apiPath(string $path = ''): string {
    return appPath('api/' . ltrim($path, '/'));
}

function uploadPath(string $path = ''): string {
    return rtrim(UPLOAD_URL, '/') . '/' . ltrim($path, '/');
}

// Format Philippine Peso
function peso(float $amount): string {
    return '₱' . number_format($amount, 2);
}

// Adjust date to end of month
function endOfMonth(string $date): string {
    $ts = strtotime($date);
    return date('Y-m-t', $ts);
}

// Log activity
function logActivity(int $userId, ?int $projectId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO activity_logs (user_id, project_id, action, details, ip_address)
             VALUES (:uid, :pid, :action, :details, :ip)"
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':pid'     => $projectId,
            ':action'  => $action,
            ':details' => $details,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
