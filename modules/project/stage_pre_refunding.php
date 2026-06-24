<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/upload.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);
$error     = '';

if (!$projectId) { header('Location: '.appPath('index.php')); exit; }

$stmt = $db->prepare("SELECT p.*, f.firm_name FROM projects p JOIN firms f ON f.id=p.firm_id WHERE p.id=:id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: '.appPath('index.php')); exit; }

function loadEx(PDO $db, int $pid, string $stage): array {
    $s = $db->prepare("SELECT document_type, file_path, original_filename FROM submissions WHERE project_id=:pid AND stage=:stage");
    $s->execute([':pid' => $pid, ':stage' => $stage]);
    $out = [];
    foreach ($s->fetchAll() as $r) { $out[$r['document_type']] = $r; }
    return $out;
}

$existing = loadEx($db, $projectId, 'pre_refunding');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $docTypes = ['certified_true_copy' => 'certified_true_copy', 'audited_financial_report' => 'audited_financial_report'];

    $db->beginTransaction();
    try {
        foreach ($docTypes as $fk => $dt) {
            if (!empty($_FILES[$fk]['name'])) {
                $up = handleUpload($_FILES[$fk], 'submissions');
                if (isset($up['error'])) { $error .= $up['error'] . ' '; continue; }
                $db->prepare("DELETE FROM submissions WHERE project_id=:pid AND stage='pre_refunding' AND document_type=:dt")->execute([':pid'=>$projectId,':dt'=>$dt]);
                $db->prepare("INSERT INTO submissions(project_id,stage,document_type,file_path,original_filename,file_size,mime_type,submitted_by) VALUES(:pid,'pre_refunding',:dt,:path,:fname,:size,:mime,:uid)")
                   ->execute([':pid'=>$projectId,':dt'=>$dt,':path'=>$up['path'],':fname'=>$up['original_filename'],':size'=>$up['file_size'],':mime'=>$up['mime_type'],':uid'=>currentUser()['id']]);
            }
        }
        if (!$error) {
            // Build refund schedule from PDCs if not already done
            $pdcCheck = $db->prepare("SELECT COUNT(*) FROM refund_schedule WHERE project_id=:pid");
            $pdcCheck->execute([':pid' => $projectId]);
            if ((int)$pdcCheck->fetchColumn() === 0) {
                $pdcs = $db->prepare("SELECT id, adjusted_date, amount FROM pdcs WHERE project_id=:pid ORDER BY pdc_number");
                $pdcs->execute([':pid' => $projectId]);
                $refIns = $db->prepare("INSERT INTO refund_schedule(project_id,pdc_id,refund_date,refund_amount) VALUES(:pid,:pdcid,:dt,:amt)");
                foreach ($pdcs->fetchAll() as $pdc) {
                    $refIns->execute([':pid'=>$projectId,':pdcid'=>$pdc['id'],':dt'=>$pdc['adjusted_date'],':amt'=>$pdc['amount']]);
                }
            }

            $db->prepare("UPDATE projects SET current_stage='refunding',status='refund',updated_at=NOW() WHERE id=:id")->execute([':id'=>$projectId]);
            $db->commit();
            header('Location: '.appPath("modules/project/stage_refunding.php?id={$projectId}"));
            exit;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error saving. Please retry.';
    }
}

$existing = loadEx($db, $projectId, 'pre_refunding');
$csrf = csrfToken();
$pageTitle  = 'Pre-Refunding Submissions';
$activePage = 'start-project';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / Pre-Refunding';

function drPre(string $label, string $key, string $dt, array $ex): string {
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
        <i class="bi bi-file-earmark-medical me-2"></i> <?= h($project['project_title']) ?> – Pre-Refunding Submissions
    </div>

    <div class="stage-timeline">
        <div class="stage-step"><div class="stage-label done">Start Project</div></div>
        <div class="stage-step"><div class="stage-label done">1st Untagging</div></div>
        <div class="stage-step"><div class="stage-label done">Final Untagging</div></div>
        <div class="stage-step"><div class="stage-label active">Pre-Refunding Submissions</div></div>
        <div class="stage-step"><div class="stage-label">Refunding</div></div>
    </div>

    <?php if ($error): ?>
        <div class="alert-ppmis error"><i class="bi bi-x-circle"></i><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= drPre('Certified True Copy of Receipt', 'certified_true_copy', 'certified_true_copy', $existing) ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body-ppmis">
                <?= drPre('Audited Financial Report', 'audited_financial_report', 'audited_financial_report', $existing) ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= h(appPath('modules/project/stage_final_untagging.php?id=' . $projectId)) ?>" class="btn-ppmis-secondary">
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
    if (input.files?.[0]) {
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
