<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/upload.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);
$error     = '';

if (!$projectId) { header('Location: '.appPath('index.php')); exit; }

$stmt = $db->prepare("SELECT p.*, f.firm_name FROM projects p JOIN firms f ON f.id = p.firm_id WHERE p.id = :id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: '.appPath('index.php')); exit; }

// Load existing
function loadExisting(PDO $db, int $pid, string $stage): array {
    $s = $db->prepare("SELECT document_type, file_path, original_filename FROM submissions WHERE project_id=:pid AND stage=:stage");
    $s->execute([':pid' => $pid, ':stage' => $stage]);
    $out = [];
    foreach ($s->fetchAll() as $r) { $out[$r['document_type']] = $r; }
    return $out;
}

$existing = loadExisting($db, $projectId, 'final_untagging');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $docTypes = ['original_receipt' => 'original_receipt', 'matrix_of_inspection' => 'matrix_of_inspection', 'cert_final_untagging' => 'cert_final_untagging'];

    $db->beginTransaction();
    try {
        foreach ($docTypes as $fk => $dt) {
            if (!empty($_FILES[$fk]['name'])) {
                $up = handleUpload($_FILES[$fk], 'submissions');
                if (isset($up['error'])) { $error .= $up['error'] . ' '; continue; }
                $db->prepare("DELETE FROM submissions WHERE project_id=:pid AND stage='final_untagging' AND document_type=:dt")->execute([':pid'=>$projectId,':dt'=>$dt]);
                $db->prepare("INSERT INTO submissions(project_id,stage,document_type,file_path,original_filename,file_size,mime_type,submitted_by) VALUES(:pid,'final_untagging',:dt,:path,:fname,:size,:mime,:uid)")
                   ->execute([':pid'=>$projectId,':dt'=>$dt,':path'=>$up['path'],':fname'=>$up['original_filename'],':size'=>$up['file_size'],':mime'=>$up['mime_type'],':uid'=>currentUser()['id']]);
            }
        }
        if (!$error) {
            $db->prepare("UPDATE projects SET current_stage='pre_refunding', updated_at=NOW() WHERE id=:id AND current_stage='final_untagging'")->execute([':id'=>$projectId]);
            $db->commit();
            header('Location: '.appPath("modules/project/stage_pre_refunding.php?id={$projectId}"));
            exit;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error saving. Please retry.';
    }
}

$existing = loadExisting($db, $projectId, 'final_untagging');
$csrf = csrfToken();
$pageTitle  = 'Final Untagging Stage';
$activePage = 'start-project';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / Final Untagging';

function docRowFinal(string $label, string $key, string $dt, array $ex): string {
    $has  = isset($ex[$dt]);
    $path = $has ? h($ex[$dt]['file_path']) : '';
    $name = $has ? h($ex[$dt]['original_filename']) : '';
    $cls  = $has ? 'has-file' : '';
    return <<<HTML
<div class="doc-row $cls" id="row-$key">
    <i class="bi bi-file-earmark-check doc-status-icon"></i>
    <span class="doc-name">$label</span>
    <div class="doc-actions">
        <button type="button" class="btn-upload" onclick="document.getElementById('f_$key').click()">
            <i class="bi bi-upload"></i> Insert Images
        </button>
        <button type="button" class="btn-preview" onclick="previewDoc('f_$key','$path','$name')">
            <i class="bi bi-eye"></i> Preview
        </button>
    </div>
</div>
<input type="file" id="f_$key" name="$key" accept="image/*,.pdf" style="display:none;"
       onchange="markDocDone('row-$key',this)">
HTML;
}

ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-patch-check me-2"></i> <?= h($project['project_title']) ?> – Final Untagging Stage
    </div>

    <div class="stage-timeline">
        <div class="stage-step"><div class="stage-label done">Start Project</div></div>
        <div class="stage-step"><div class="stage-label done">1st Untagging</div></div>
        <div class="stage-step"><div class="stage-label active">Final Untagging</div></div>
        <div class="stage-step"><div class="stage-label">Pre-Refunding Submissions</div></div>
        <div class="stage-step"><div class="stage-label">Refunding</div></div>
    </div>

    <?php if ($error): ?>
        <div class="alert-ppmis error"><i class="bi bi-x-circle"></i><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRowFinal('Original Copy of the Receipt', 'original_receipt', 'original_receipt', $existing) ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRowFinal('Matrix of Inspection', 'matrix_of_inspection', 'matrix_of_inspection', $existing) ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= docRowFinal('Certificate of Final Untagging', 'cert_final_untagging', 'cert_final_untagging', $existing) ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= h(appPath('modules/project/stage_first_untagging.php?id=' . $projectId)) ?>" class="btn-ppmis-secondary">
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
        document.getElementById(rowId)?.classList.add('has-file');
        Toast.show(input.files[0].name + ' selected.', 'success');
    }
}
function previewDoc(inputId, existingPath, existingName) {
    const input = document.getElementById(inputId);
    if (input?.files?.[0]) {
        previewFile(URL.createObjectURL(input.files[0]), input.files[0].name);
    } else if (existingPath) {
        previewFile(existingPath, existingName);
    } else {
        Toast.show('No file available.', 'error');
    }
}
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>
