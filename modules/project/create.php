<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/upload.php';
requireLogin();

$db    = getDB();
$error = '';
$success = '';

// Load proponents for dropdown
$proponents = $db->query("SELECT id, proponent_name FROM proponents WHERE is_active = 1 ORDER BY proponent_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $proponentId      = (int)($_POST['proponent_id'] ?? 0);
    $projectTitle = sanitize($_POST['project_title'] ?? '');
    $fundAmount   = (float)($_POST['fund_amount'] ?? 0);

    if (!$proponentId || $projectTitle === '' || $fundAmount <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // Handle approval letter
        $approvalLetterPath = null;
        if (!empty($_FILES['approval_letter']['name'])) {
            $up = handleUpload($_FILES['approval_letter'], 'approval_letters');
            if (isset($up['error'])) {
                $error = $up['error'];
            } else {
                $approvalLetterPath = $up['path'];
            }
        }

        // Handle PPIS Letter
        $ppisPath = null;
        if (!empty($_FILES['ppis_letter']['name'])) {
            $up2 = handleUpload($_FILES['ppis_letter'], 'submissions');
            if (isset($up2['error'])) {
                $error = ($error ? $error . ' ' : '') . $up2['error'];
            } else {
                $ppisPath = $up2['path'];
            }
        }

        if (!$error) {
            $db->beginTransaction();
            try {
                // Insert project
                $stmt = $db->prepare("
                    INSERT INTO projects (proponent_id, project_title, fund_amount, approval_letter, current_stage, status, created_by)
                    VALUES (:fid, :title, :amount, :img, 'approval', 'active', :uid)
                ");
                $stmt->execute([
                    ':fid'    => $proponentId,
                    ':title'  => $projectTitle,
                    ':amount' => $fundAmount,
                    ':img'    => $approvalLetterPath,
                    ':uid'    => currentUser()['id'],
                ]);
                $projectId = (int)$db->lastInsertId();

                logActivity(currentUser()['id'],$projectId,'SUBMIT_APPROVAL_LETTER','approval_letter',$approvalLetterPath,'Submission of approval letter');

                // Insert PPIS submission if uploaded
                if ($ppisPath) {
                    $sub = $db->prepare("
                        INSERT INTO submissions (project_id, stage, document_type, file_path, original_filename, submitted_by)
                        VALUES (:pid, 'approval', 'ppis_letter', :path, :fname, :uid)
                    ");
                    $sub->execute([
                        ':pid'   => $projectId,
                        ':path'  => $ppisPath,
                        ':fname' => basename($_FILES['ppis_letter']['name']),
                        ':uid'   => currentUser()['id'],
                    ]);
                }

                logActivity(currentUser()['id'],$projectId,'SUBMIT_PPIS_LETTER','ppis_letter',$ppisPath,'Submission of approval letter');

                $db->commit();
                logActivity(currentUser()['id'], $projectId, 'CREATE_PROJECT', "Created project: $projectTitle");
                header('Location: '.appPath("modules/project/stage_first_untagging.php?id={$projectId}&new=1"));
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                error_log($e->getMessage());
                $error = 'Failed to save project. Please try again.';
            }
        }
    }
}

$csrf       = csrfToken();
$pageTitle  = 'Start Project – Approval Stage';
$activePage = 'start-project';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / <span>Start Project</span>';
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-clipboard-plus me-2"></i> Approval Stage
    </div>

    <!-- Stage Timeline -->
    <div class="stage-timeline">
        <div class="stage-step"><div class="stage-label active">Start Project</div></div>
        <div class="stage-step"><div class="stage-label">1st Untagging</div></div>
        <div class="stage-step"><div class="stage-label">Final Untagging</div></div>
        <div class="stage-step"><div class="stage-label">Pre-Refunding Submissions</div></div>
        <div class="stage-step"><div class="stage-label">Refunding</div></div>
    </div>

    <?php if ($error): ?>
        <div class="alert-ppmis error"><i class="bi bi-x-circle"></i><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <!-- Choose Proponent -->
        <div class="card mb-4">
            <div class="card-header-ppmis"><i class="bi bi-building me-2"></i>Choose Proponent</div>
            <div class="card-body-ppmis">
                <label class="form-label-ppmis">Select Proponent *</label>
                <select name="proponent_id" class="ppmis-select" required>
                    <option value="">-- Select a Proponent --</option>
                    <?php foreach ($proponents as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"
                            <?= ((int)($_POST['proponent_id'] ?? 0) === (int)$f['id']) ? 'selected' : '' ?>>
                            <?= h($f['proponent_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($proponents)): ?>
                    <div style="margin-top:8px;font-size:12px;color:#888;">
                        No proponents found. <a href="<?= h(appPath('modules/proponents/create.php')) ?>">Add a proponent first.</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Project Title + Image -->
        <div class="card mb-4">
            <div class="card-header-ppmis"><i class="bi bi-image me-2"></i>Approval Letter</div>
            <div class="card-body-ppmis">
                <div class="image-upload-box mb-4">
                    <button type="button" class="insert-image-btn" onclick="document.getElementById('projectImageInput').click()">
                        <i class="bi bi-upload"></i> Insert Image
                    </button>
                    <input type="file" id="projectImageInput" name="approval_letter"
                           accept="image/jpeg,image/png,image/gif" style="display:none;"
                           onchange="previewProjectImage(this)">
                    <div class="image-preview-area" id="projectImgPreview">
                        <span>Image Preview</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label-ppmis">Project Title *</label>
                        <input type="text" name="project_title" class="ppmis-input"
                               value="<?= h($_POST['project_title'] ?? '') ?>"
                               placeholder="Enter full project title" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-ppmis">Project Fund Amount (₱) *</label>
                        <input type="number" name="fund_amount" class="ppmis-input"
                               value="<?= h($_POST['fund_amount'] ?? '') ?>"
                               placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- PPIS Letter Upload -->
        <div class="card mb-4">
            <div class="card-header-ppmis"><i class="bi bi-file-earmark-text me-2"></i>PPIS Letter</div>
            <div class="card-body-ppmis">
                <div class="doc-row" id="doc-row-ppis">
                    <i class="bi bi-file-earmark-check doc-status-icon"></i>
                    <span class="doc-name">PPIS Letter / Approval Letter</span>
                    <div class="doc-actions">
                        <button type="button" class="btn-upload" onclick="document.getElementById('ppisInput').click()">
                            <i class="bi bi-upload"></i> Insert Image
                        </button>
                        <button type="button" class="btn-preview" onclick="previewSelectedFile('ppisInput', 'PPIS Letter')">
                            <i class="bi bi-eye"></i> Preview
                        </button>
                    </div>
                </div>
                <input type="file" id="ppisInput" name="ppis_letter"
                       accept="image/jpeg,image/png,image/gif,application/pdf" style="display:none;"
                       onchange="markDocDone('doc-row-ppis', this)">
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
function previewProjectImage(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = (e) => {
        const area = document.getElementById('projectImgPreview');
        area.innerHTML = `<img src="${e.target.result}" alt="Project Image">`;
    };
    reader.readAsDataURL(file);
}

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
