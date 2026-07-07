<?php
// api/submit_pdcs.php — Handles PDC mass upload from the modal
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/upload.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// CSRF check (token sent as POST field)
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token mismatch.']);
    exit;
}

$projectId = (int)($_POST['project_id'] ?? 0);
if (!$projectId) {
    echo json_encode(['error' => 'Invalid project.']);
    exit;
}

// Verify project exists
$db   = getDB();
$stmt = $db->prepare("SELECT id FROM projects WHERE id = :id");
$stmt->execute([':id' => $projectId]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Project not found.']);
    exit;
}

$errors   = [];
$inserted = 0;

$db->beginTransaction();
try {
    // Clear existing PDCs for this project (allow re-upload)
    $db->prepare("DELETE FROM pdcs WHERE project_id = :pid")->execute([':pid' => $projectId]);
    $db->prepare("DELETE FROM refund_schedule WHERE project_id = :pid")->execute([':pid' => $projectId]);

    // Find how many PDC slots were submitted (up to 36)
    for ($i = 1; $i <= 36; $i++) {
        $fileKey = "pdc_file_{$i}";
        $dateKey = "pdc_date_{$i}";
        $amtKey  = "pdc_amount_{$i}";

        // Skip if this slot wasn't submitted
        if (empty($_FILES[$fileKey]['name']) && empty($_POST[$dateKey])) {
            continue;
        }

        $date   = $_POST[$dateKey] ?? '';
        $amount = (float)($_POST[$amtKey] ?? 0);

        if (!$date && $amount <= 0) {
            $errors[] = "PDC $i: Date and amount are required.";
            continue;
        }

        // Validate date
        $dateTs = strtotime($date);
        if (!$dateTs) {
            $errors[] = "PDC $i: Invalid date.";
            continue;
        }

        // Adjust to end of month
        $adjustedDate = date('Y-m-t', $dateTs);

        // Handle file upload (optional per PDC slot)
        $filePath         = null;
        $originalFilename = null;
        if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $up = handleUpload($_FILES[$fileKey], 'pdcs');
            if (isset($up['error'])) {
                $errors[] = "PDC $i file: " . $up['error'];
                continue;
            }
            $filePath         = $up['path'];
            $originalFilename = $up['original_filename'];
        }

        // Insert PDC
        $ins = $db->prepare("
            INSERT INTO pdcs (project_id, pdc_number, file_path, original_filename, check_date, adjusted_date, amount, submitted_by)
            VALUES (:pid, :num, :path, :fname, :cdate, :adate, :amt, :uid)
        ");
        $ins->execute([
            ':pid'   => $projectId,
            ':num'   => $i,
            ':path'  => $filePath,
            ':fname' => $originalFilename,
            ':cdate' => $date,
            ':adate' => $adjustedDate,
            ':amt'   => $amount,
            ':uid'   => currentUser()['id'],
        ]);

        $pdcId = (int)$db->lastInsertId();

        // Insert refund schedule entry
        $refIns = $db->prepare("
            INSERT INTO refund_schedule (project_id, pdc_id, refund_date, refund_amount)
            VALUES (:pid, :pdcid, :dt, :amt)
        ");
        $refIns->execute([
            ':pid'   => $projectId,
            ':pdcid' => $pdcId,
            ':dt'    => $adjustedDate,
            ':amt'   => $amount,
        ]);

        logActivity(
            currentUser()['id'],
            $projectId,
            'SUBMIT_PDC',
            'pdc',
            $filePath ?? '',
            "PDC #$i submitted (check date: $date, amount: $amount)"
        );

        $inserted++;
    }

    if ($inserted === 0) {
        $db->rollBack();
        echo json_encode(['error' => 'No valid PDCs found. Please fill in date and amount for at least one PDC.']);
        exit;
    }

    // Update project stage if still in approval
    $advanced = $db->prepare("
        UPDATE projects SET current_stage = 'first_untagging', updated_at = NOW()
        WHERE id = :id AND current_stage = 'approval'
    ");
    $advanced->execute([':id' => $projectId]);

    $db->commit();

    if ($advanced->rowCount() > 0) {
        logActivity(
            currentUser()['id'],
            $projectId,
            'STAGE_ADVANCE_APPROVAL',
            'first_untagging',
            '',
            'PDCs submitted, advanced from Approval to 1st Untagging.'
        );
    }

    logActivity(
        currentUser()['id'],
        $projectId,
        'SUBMIT_PDCS_BATCH',
        'pdc',
        '',
        "$inserted PDC(s) submitted."
    );

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'errors'   => $errors,
        'message'  => "$inserted PDC(s) submitted successfully.",
    ]);

} catch (Exception $e) {
    $db->rollBack();
    error_log($e->getMessage());
    echo json_encode(['error' => 'Server error. Please try again.']);
}