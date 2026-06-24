<?php
// includes/upload.php - Secure File Upload Handler

require_once __DIR__ . '/../config/database.php';

/**
 * Handle a single file upload
 * Returns ['success'=>true,'path'=>'...','filename'=>'...'] or ['error'=>'...']
 */
function handleUpload(array $file, string $subdir = 'documents'): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File exceeds maximum size of 10MB.'];
    }

    // Validate mime type using finfo (not trusting $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return ['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF.'];
    }

    // Build safe destination path
    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'application/pdf' => 'pdf',
        default => 'bin',
    };

    $destDir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $uniqueName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath   = $destDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['error' => 'Failed to save file.'];
    }

    return [
        'success'           => true,
        'path'              => UPLOAD_URL . $subdir . '/' . $uniqueName,
        'filename'          => $uniqueName,
        'original_filename' => basename($file['name']),
        'mime_type'         => $mimeType,
        'file_size'         => $file['size'],
    ];
}
