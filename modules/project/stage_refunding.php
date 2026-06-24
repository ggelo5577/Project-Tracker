<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);

if (!$projectId) { header('Location: '.appPath('index.php')); exit; }

$stmt = $db->prepare("SELECT p.*, f.firm_name FROM projects p JOIN firms f ON f.id=p.firm_id WHERE p.id=:id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: '.appPath('index.php')); exit; }

// Load refund schedule
$refStmt = $db->prepare("SELECT * FROM refund_schedule WHERE project_id=:pid ORDER BY refund_date");
$refStmt->execute([':pid' => $projectId]);
$refunds = $refStmt->fetchAll();

// Check if all done
$allDone = count($refunds) > 0 && array_reduce($refunds, fn($c, $r) => $c && $r['is_done'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish']) && $allDone) {
    verifyCsrf();
    $db->prepare("UPDATE projects SET current_stage='completed',status='completed',updated_at=NOW() WHERE id=:id")->execute([':id'=>$projectId]);
    logActivity(currentUser()['id'], $projectId, 'PROJECT_COMPLETED', 'All refunds done – project completed.');
    header('Location: '.appPath('index.php?completed=1'));
    exit;
}

$csrf = csrfToken();
$pageTitle  = 'Refunding Stage';
$activePage = 'start-project';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / Refunding Stage';

// Total/paid
$totalAmt = array_sum(array_column($refunds, 'refund_amount'));
$paidAmt  = array_sum(array_map(fn($r) => $r['is_done'] ? $r['refund_amount'] : 0, $refunds));
$paidCnt  = count(array_filter($refunds, fn($r) => $r['is_done']));

ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-cash-coin me-2"></i> <?= h($project['project_title']) ?> – Refunding Stage
    </div>

    <div class="stage-timeline">
        <div class="stage-step"><div class="stage-label done">Start Project</div></div>
        <div class="stage-step"><div class="stage-label done">1st Untagging</div></div>
        <div class="stage-step"><div class="stage-label done">Final Untagging</div></div>
        <div class="stage-step"><div class="stage-label done">Pre-Refunding Submissions</div></div>
        <div class="stage-step"><div class="stage-label active">Refunding</div></div>
    </div>

    <!-- Summary Bar -->
    <div class="card mb-4" style="border-left:4px solid #0F4C81;">
        <div class="card-body-ppmis">
            <div class="d-flex gap-4 flex-wrap">
                <div>
                    <div class="stat-label">Total Refunds</div>
                    <div style="font-size:20px;font-weight:800;color:#0F4C81;"><?= count($refunds) ?></div>
                </div>
                <div>
                    <div class="stat-label">Total Amount</div>
                    <div style="font-size:20px;font-weight:800;color:#0F4C81;"><?= peso($totalAmt) ?></div>
                </div>
                <div>
                    <div class="stat-label">Paid</div>
                    <div style="font-size:20px;font-weight:800;color:#00C896;"><?= peso($paidAmt) ?></div>
                </div>
                <div>
                    <div class="stat-label">Remaining</div>
                    <div style="font-size:20px;font-weight:800;color:#E87722;"><?= peso($totalAmt - $paidAmt) ?></div>
                </div>
                <div>
                    <div class="stat-label">Progress</div>
                    <div style="font-size:20px;font-weight:800;color:#0F4C81;">
                        <?= $paidCnt ?>/<?= count($refunds) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Refund Schedule -->
    <?php if (empty($refunds)): ?>
        <div class="alert-ppmis info">
            <i class="bi bi-info-circle"></i>
            No refund schedule found. Please go back and submit PDCs first.
        </div>
    <?php else: ?>
        <input type="hidden" name="csrf_token" id="csrf_hidden" value="<?= h($csrf) ?>">

        <?php foreach ($refunds as $ref): ?>
        <div class="refund-row <?= $ref['is_done'] ? 'is-done' : '' ?>" id="refund-<?= (int)$ref['id'] ?>">
            <div>
                <div class="refund-field-label">Date</div>
                <span class="refund-date-badge"><?= h(date('d/m/Y', strtotime($ref['refund_date']))) ?></span>
            </div>
            <div>
                <div class="refund-field-label">Refund Amount</div>
                <span class="refund-amount-badge"><?= peso((float)$ref['refund_amount']) ?></span>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;align-items:center;">
                <button class="btn-notify <?= $ref['is_notified'] ? 'notified' : '' ?>"
                        onclick="notifyRefund(<?= (int)$ref['id'] ?>, this)"
                        <?= $ref['is_notified'] ? 'disabled' : '' ?>>
                    <?= $ref['is_notified'] ? 'Notified' : 'Notify' ?>
                </button>
                <div class="refund-field-label" style="font-size:10px;">Notify</div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;align-items:center;">
                <div class="btn-done-check <?= $ref['is_done'] ? 'checked' : '' ?>"
                     onclick="markRefundDone(<?= (int)$ref['id'] ?>, this)"
                     role="button" tabindex="0"
                     aria-label="Mark refund <?= (int)$ref['id'] ?> as done">
                    <?php if ($ref['is_done']): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                </div>
                <div class="refund-field-label" style="font-size:10px;">Done</div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Finish -->
        <div class="form-actions">
            <a href="<?= h(appPath('modules/project/stage_pre_refunding.php?id=' . $projectId)) ?>" class="btn-ppmis-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="finish" value="1">
                <button type="submit" class="btn-ppmis-primary" <?= !$allDone ? 'disabled title="All refunds must be marked Done first."' : '' ?>>
                    Finish <i class="bi bi-flag-fill"></i>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Override to use the hidden input value
function getCsrf() {
    return document.getElementById('csrf_hidden')?.value || '';
}

function notifyRefund(refundId, btn) {
    fetch(window.APP_BASE + '/api/refund_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'notify', refund_id: refundId, csrf_token: getCsrf() })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            btn.classList.add('notified');
            btn.textContent = 'Notified';
            btn.disabled = true;
            Toast.show('Notification sent.', 'success');
        } else {
            Toast.show(data.error || 'Failed.', 'error');
        }
    });
}

function markRefundDone(refundId, checkBtn) {
    if (checkBtn.classList.contains('checked')) return;
    fetch(window.APP_BASE + '/api/refund_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'done', refund_id: refundId, csrf_token: getCsrf() })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            checkBtn.classList.add('checked');
            checkBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
            checkBtn.closest('.refund-row')?.classList.add('is-done');
            Toast.show('Marked as done.', 'success');
            // Check if all done
            const undone = document.querySelectorAll('.btn-done-check:not(.checked)').length;
            if (undone === 0) {
                Toast.show('All refunds complete! You can now Finish the project.', 'success');
                setTimeout(() => location.reload(), 1200);
            }
        } else {
            Toast.show(data.error || 'Failed.', 'error');
        }
    });
}
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>
