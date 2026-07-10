<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/upload.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);
$error     = '';
if (!$projectId) {
    header('Location: ' . appPath('index.php'));
    exit;
}

$stmt = $db->prepare("
    SELECT p.*, f.firm_name, f.contact_person, f.contact_email, f.contact_phone
    FROM projects p
    JOIN firms f ON f.id = p.firm_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
$mime_type = '';

if (isset($_FILEs['supporting_docs'])) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['supporting_docs']['tmp_name']);
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrf();
    $notes = h($_POST['notes']);

    $imagePath = null;
    if (!empty($_FILES['supporting_docs'])) {
        $up = handleUpload($_FILES['supporting_docs'], 'termination_documents');
        if (isset($up['error'])) {
            $error = $up['error'];
        } else {
            $imagePath = $up['path'];
        }
    }

    if ($error == '') {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
            INSERT INTO submissions
            (project_id,stage,document_type,file_path,original_filename,file_size,mime_type,submitted_by,notes)
            Values
            (:pId,'terminated','supporting_documents',:path,:file_name,:size,:mime,:user,:notes);
            ");
            $stmt->execute([
                ':pId'       => $projectId,
                ':path'      => $imagePath,
                ':file_name' => basename($_FILES['supporting_docs']['name']),
                ':size'      => $_FILES['supporting_docs']['size'],
                ':mime'      => $mime_type,
                ':user'      => currentUser()['id'],
                ':notes'     => $notes
            ]);




            $updateProject = $db->prepare("UPDATE projects SET current_stage = 'terminated', status = 'terminated' where id = :id");
            $updateProject->execute([
                ':id' => $projectId
            ]);
            
            $action = 'Project Termination';
            $details = 'Terminated a project. See project for details.';
            logActivity(currentUser()['id'], $projectId, $action, $details);

            $db->commit();
            header('Location: ' . APP_BASE . '/modules/progress/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
            $error = "Query Failed. Check error logs for query errors";
        }
    }
}

$csrf       = csrfToken();
$pageTitle  = h($project['project_title']) . ' — Terminate Project';
$activePage = 'progress';
$breadcrumb = '<a href="' . h(appPath('index.php')) . '">Dashboard</a> / <a href="' . h(appPath('modules/progress/index.php')) . '">Progress</a> / ' . h($project['project_title']);
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-bar-chart-steps me-2"></i> Terminate <?php echo h($project['project_title']) ?>
    </div>

    <?php if ($error): ?>
        <div class="alert-ppmis error"><i class="bi bi-x-circle"></i><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <div class="card mb-4">
            <div class="card-header-ppmis"><i class="bi bi-file-earmark-text me-2"></i>Add Supporting Documents</div>
            <div class="card-body-ppmis">
                <div class="doc-row" id="doc-row-supporting">
                    <i class="bi bi-file-earmark-check doc-status-icon"></i>
                    <span class="doc-name">Supporting Documents</span>
                    <div class="doc-actions">
                        <button type="button" class="btn-upload" onclick="document.getElementById('docInput').click()">
                            <i class="bi bi-upload"></i> Insert Image
                        </button>
                        <button type="button" class="btn-preview" onclick="previewSelectedFile('docInput','Supporting Documents')">
                            <i class="bi bi-eye"></i>Preview
                        </button>
                    </div>
                </div>
                <input type="file" id="docInput" name="supporting_docs"
                    accept="image/jpeg,image/png,image/gif,application/pdf" style="display:none;"
                    onchange="markDocDone('doc-row-supporting', this)" required>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header-ppmis"><i class="bi bi-file-earmark-text me-2"></i>Add Notes For Termination</div>
            <div class="card-body-ppmis">
                <div class="doc-actions">
                    <label for="notes">Reasons for terminating the project:</label>
                    <input type="text" class="form-control w-100" name="notes" style="border-color:black">

                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="<?= h(appPath('index.php')) ?>" class="btn-ppmis-secondary">
                <i class="bi bi-arrow-left"></i> Cancel
            </a>
            <button type="submit" class="btn-ppmis-primary">
                Submit <i class="bi bi-arrow-right"></i> Next
            </button>
        </div>
    </form>
</div>

<script>
    function markDocDone(rowId, input) {
        if (input.files && input.files[0]) {
            const row = document.getElementById(rowId);
            if (row) row.classList.add('has-file');
            Toast.show(input.files[0].name + ' selected.', 'success');
        }
    }

    function previewSelectedFile(inputId, label) {
        const input = document.getElementById(inputId);
        if (!input || !input.files || !input.files[0]) {
            Toast.show('No file selected for ' + label, 'error');
            return;
        }
        const file = input.files[0];
        const url = URL.createObjectURL(file);
        previewFile(url, file.name);
    }
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>