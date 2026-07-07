<?php
// api/refund_action.php — Handles Notify and Done actions on refund schedule rows
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Read JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    // Fallback to POST form data
    $body = $_POST;
}

// CSRF check
$token = $body['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token mismatch.']);
    exit;
}

$action   = $body['action']    ?? '';
$refundId = (int)($body['refund_id'] ?? 0);

if (!$refundId || !in_array($action, ['notify', 'done'], true)) {
    echo json_encode(['error' => 'Invalid request parameters.']);
    exit;
}

$db = getDB();

// Fetch the refund row and verify ownership (project belongs to our system)
$stmt = $db->prepare("
    SELECT rs.*, p.id AS project_id
    FROM refund_schedule rs
    JOIN projects p ON p.id = rs.project_id
    WHERE rs.id = :id
");
$stmt->execute([':id' => $refundId]);
$refund = $stmt->fetch();

if (!$refund) {
    echo json_encode(['error' => 'Refund record not found.']);
    exit;
}

try {
    if ($action === 'notify') {
        if ($refund['is_notified']) {
            echo json_encode(['success' => true, 'message' => 'Already notified.']);
            exit;
        }

        $db->prepare("
            UPDATE refund_schedule
            SET is_notified = 1, notified_at = NOW()
            WHERE id = :id
        ")->execute([':id' => $refundId]);

        // Also mark PDC as notified if linked
        if ($refund['pdc_id']) {
            $db->prepare("UPDATE pdcs SET is_notified = 1, notified_at = NOW() WHERE id = :id")
               ->execute([':id' => $refund['pdc_id']]);
        }

        logActivity(
            currentUser()['id'],
            $refund['project_id'],
            'REFUND_NOTIFY',
            null,
            '',
            "Notified refund #$refundId — " . peso((float)$refund['refund_amount'])
        );

        echo json_encode(['success' => true, 'message' => 'Notification sent.']);

    } elseif ($action === 'done') {
        if ($refund['is_done']) {
            echo json_encode(['success' => true, 'message' => 'Already marked done.']);
            exit;
        }

        $db->prepare("
            UPDATE refund_schedule
            SET is_done = 1, done_at = NOW()
            WHERE id = :id
        ")->execute([':id' => $refundId]);

        // Mark PDC as paid if linked
        if ($refund['pdc_id']) {
            $db->prepare("UPDATE pdcs SET is_paid = 1, paid_at = NOW() WHERE id = :id")
               ->execute([':id' => $refund['pdc_id']]);
        }

        logActivity(
            currentUser()['id'],
            $refund['project_id'],
            'REFUND_DONE',
            null,
            '',
            "Marked refund #$refundId as done — " . peso((float)$refund['refund_amount'])
        );

        // Check if ALL refunds for this project are now done → auto-complete project
        $checkStmt = $db->prepare("
            SELECT COUNT(*) AS total, SUM(is_done) AS done
            FROM refund_schedule WHERE project_id = :pid
        ");
        $checkStmt->execute([':pid' => $refund['project_id']]);
        $check = $checkStmt->fetch();

        $allDone = (int)$check['total'] > 0 && (int)$check['done'] === (int)$check['total'];

        echo json_encode([
            'success'  => true,
            'message'  => 'Marked as done.',
            'all_done' => $allDone,
        ]);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'Server error. Please try again.']);
}