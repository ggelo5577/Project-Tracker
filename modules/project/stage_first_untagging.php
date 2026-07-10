<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/upload.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);
$error     = '';

if (!$projectId) { header('Location: '.appPath('index.php')); exit; }

// Load project
$stmt = $db->prepare("SELECT pj.*, pn.proponent_name FROM projects pj JOIN proponents pn ON pn.id = pj.proponent_id WHERE pj.id = :id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: '.appPath('index.php')); exit; }

// Load existing submissions for this stage
$subStmt = $db->prepare("SELECT document_type, file_path, original_filename FROM submissions WHERE project_id = :pid AND stage = 'first_untagging'");
$subStmt->execute([':pid' => $projectId]);
$existing = [];
foreach ($subStmt->fetchAll() as $sub) {
    $existing[$sub['document_type']] = $sub;
}

// Load PDCs
$pdcStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM pdcs WHERE project_id = :pid");
$pdcStmt->execute([':pid' => $projectId]);
$pdcCount = (int)$pdcStmt->fetch()['cnt'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $docTypes = [
        'release_letter'       => 'release_letter',
        'revised_annex_d'      => 'revised_annex_d',
        'ipo'                  => 'ipo',
        'acknowledgement'      => 'acknowledgement',
        'cert_first_untagging' => 'cert_first_untagging',
    ];

    $db->beginTransaction();
    try {
        foreach ($docTypes as $fileKey => $docType) {
            if (!empty($_FILES[$fileKey]['name'])) {
                $up = handleUpload($_FILES[$fileKey], 'submissions');
                if (isset($up['error'])) { $error .= $up['error'] . ' '; continue; }

                // Upsert: delete old, insert new
                $del = $db->prepare("DELETE FROM submissions WHERE project_id=:pid AND stage='first_untagging' AND document_type=:dt");
                $del->execute([':pid' => $projectId, ':dt' => $docType]);

                $ins = $db->prepare("INSERT INTO submissions (project_id, stage, document_type, file_path, original_filename, file_size, mime_type, submitted_by)
                    VALUES (:pid,'first_untagging',:dt,:path,:fname,:size,:mime,:uid)");
                $ins->execute([
                    ':pid'   => $projectId,
                    ':dt'    => $docType,
                    ':path'  => $up['path'],
                    ':fname' => $up['original_filename'],
                    ':size'  => $up['file_size'],
                    ':mime'  => $up['mime_type'],
                    ':uid'   => currentUser()['id'],
                ]);

                logActivity(
                    currentUser()['id'],
                    $projectId,
                    'SUBMIT_' . strtoupper($docType),
                    $docType,
                    $up['path'],
                    'Submission of ' . $docType
                );
            }
        }

        if (!$error) {
            // Advance stage if docs present and PDCs done
            if ($pdcCount > 0) {
                $adv = $db->prepare("UPDATE projects SET current_stage='final_untagging', updated_at=NOW() WHERE id=:id AND current_stage='first_untagging'");
                $adv->execute([':id' => $projectId]);

                logActivity(
                    currentUser()['id'],
                    $projectId,
                    'STAGE_ADVANCE_FIRST_UNTAGGING',
                    'first_untagging',
                    '',
                    '1st Untagging stage completed, advanced to Final Untagging.'
                );
            }
            $db->commit();
            header('Location: '.appPath("modules/project/stage_final_untagging.php?id={$projectId}"));
            exit;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
        $error = 'Failed to save. Please try again.';
    }
}

// Reload existing after POST
$subStmt->execute([':pid' => $projectId]);
$existing = [];
foreach ($subStmt->fetchAll() as $sub) {
    $existing[$sub['document_type']] = $sub;
}

$csrf       = csrfToken();
$pageTitle  = '1st Untagging Stage';
$activePage = 'start-project';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / <a href="'.h(appPath('modules/progress/view.php?id='.$projectId)).'">'.h($project['project_title']).'</a> / 1st Untagging';

// Helper to render a doc row
function docRow(string $label, string $fileKey, string $docType, array $existing, int $projectId): string {
    $has  = isset($existing[$docType]);
    $path = $has ? h($existing[$docType]['file_path']) : '';
    $name = $has ? h($existing[$docType]['original_filename']) : '';
    $cls  = $has ? 'has-file' : '';
    return <<<HTML
<div class="doc-row $cls" id="row-$fileKey">
    <i class="bi bi-file-earmark-check doc-status-icon"></i>
    <span class="doc-name">$label</span>
    <div class="doc-actions">
        <button type="button" class="btn-upload" onclick="document.getElementById('f_$fileKey').click()">
            <i class="bi bi-upload"></i> Insert Image
        </button>
        <button type="button" class="btn-preview" onclick="previewDoc('f_$fileKey','$path','$name')">
            <i class="bi bi-eye"></i> Preview
        </button>
    </div>
</div>
<input type="file" id="f_$fileKey" name="$fileKey" accept="image/*,.pdf" style="display:none;"
       onchange="markDocDone('row-$fileKey',this)">
HTML;
}

ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-layers me-2"></i> <?= h($project['project_title']) ?> – 1st Untagging Stage
    </div>

    <!-- Stage Timeline -->
    <div class="stage-timeline">
        <div class="stage-step"><div class="stage-label done">Start Project</div></div>
        <div class="stage-step"><div class="stage-label active">1st Untagging</div></div>
        <div class="stage-step"><div class="stage-label">Final Untagging</div></div>
        <div class="stage-step"><div class="stage-label">Pre-Refunding Submissions</div></div>
        <div class="stage-step"><div class="stage-label">Refunding</div></div>
    </div>

    <?php if (isset($_GET['new'])): ?>
        <div class="alert-ppmis success"><i class="bi bi-check-circle"></i>Project created successfully! Now upload the 1st Untagging documents.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-ppmis error"><i class="bi bi-x-circle"></i><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRow('Release Letter', 'release_letter', 'release_letter', $existing, $projectId) ?>
            </div>
        </div>

        <!-- PDCs (special – opens modal) -->
        <div class="card mb-3">
            <div class="card-body-ppmis">
                <div class="doc-row <?= $pdcCount > 0 ? 'has-file' : '' ?>" id="doc-row-pdc">
                    <i class="bi bi-file-earmark-check doc-status-icon"></i>
                    <span class="doc-name">PDCs <small style="color:#888;">(<?= $pdcCount ?> submitted)</small></span>
                    <div class="doc-actions">
                        <button type="button" class="btn-upload" onclick="PDCModal.open(<?= $projectId ?>)">
                            <i class="bi bi-upload"></i> Insert Images
                        </button>
                        <button type="button" class="btn-preview" onclick="viewPDCs(<?= $projectId ?>)">
                            <i class="bi bi-eye"></i> Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRow('Revised Annex D', 'revised_annex_d', 'revised_annex_d', $existing, $projectId) ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRow('IPO', 'ipo', 'ipo', $existing, $projectId) ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRow('Acknowledgement', 'acknowledgement', 'acknowledgement', $existing, $projectId) ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRow('Certificate Of 1st Untagging', 'cert_first_untagging', 'cert_first_untagging', $existing, $projectId) ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= h(appPath('modules/project/create.php')) ?>" class="btn-ppmis-secondary">
                <i class="bi bi-arrow-left"></i> Back
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

function previewDoc(inputId, existingPath, existingName) {
    const input = document.getElementById(inputId);
    if (input && input.files && input.files[0]) {
        const url = URL.createObjectURL(input.files[0]);
        previewFile(url, input.files[0].name);
    } else if (existingPath) {
        previewFile(existingPath, existingName);
    } else {
        Toast.show('No file available to preview.', 'error');
    }
}

function viewPDCs(projectId) {
    window.open(window.APP_BASE + '/api/get_pdcs.php?project_id=' + projectId, '_blank');
}
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>