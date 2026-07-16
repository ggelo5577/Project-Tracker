<?php
// api/get_pdcs.php — View PDC list for a project
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['project_id'] ?? 0);

if (!$projectId) { die('Invalid project.'); }

$stmt = $db->prepare("SELECT p.project_title, pn.proponent_name FROM projects pj JOIN proponents pn ON pn.id=pj.proponents_id WHERE pj.id=:id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { die('Project not found.'); }

$pdcStmt = $db->prepare("SELECT * FROM pdcs WHERE project_id=:pid ORDER BY pdc_number");
$pdcStmt->execute([':pid' => $projectId]);
$pdcs = $pdcStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDCs – <?= h($project['project_title']) ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Segoe UI',system-ui,sans-serif; background:#F5F7FA; padding:24px; font-size:14px; }
        .page-title { background:linear-gradient(135deg,#E87722,#d4641a); color:#fff; border-radius:12px; padding:14px 20px; margin-bottom:20px; font-weight:700; font-size:16px; }
        .pdc-card { background:#fff; border:1.5px solid #dde3ea; border-radius:12px; padding:14px 16px; margin-bottom:12px; }
        .pdc-header { font-weight:700; color:#0F4C81; margin-bottom:10px; font-size:14px; }
        .pdc-img { width:100%; max-height:160px; object-fit:cover; border-radius:8px; cursor:pointer; border:1px solid #e9ecef; }
        .badge-date { background:#E87722; color:#fff; padding:4px 12px; border-radius:8px; font-size:12px; font-weight:700; }
        .badge-amt  { background:#0F4C81; color:#fff; padding:4px 12px; border-radius:8px; font-size:12px; font-weight:700; }
        .badge-adj  { background:#e3f0ff; color:#0F4C81; padding:4px 12px; border-radius:8px; font-size:11px; font-weight:700; }
        .status-paid { color:#00C896; font-weight:700; }
        .status-pending { color:#aaa; }
    </style>
</head>
<body>
<div class="page-title">
    <i class="bi bi-credit-card me-2"></i>
    PDC Submissions — <?= h($project['project_title']) ?>
    <small style="font-weight:400;margin-left:8px;"><?= h($project['proponent_name']) ?></small>
</div>

<?php if (empty($pdcs)): ?>
    <div style="text-align:center;padding:48px;color:#aaa;">
        <i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:10px;"></i>
        No PDCs submitted yet.
    </div>
<?php else: ?>
    <div style="margin-bottom:12px;color:#666;font-size:13px;">
        <strong><?= count($pdcs) ?></strong> PDC(s) submitted —
        Total: <strong style="color:#0F4C81;"><?= peso(array_sum(array_column($pdcs,'amount'))) ?></strong>
    </div>

    <div class="row g-3">
    <?php foreach ($pdcs as $pdc): ?>
    <div class="col-md-6 col-lg-4">
        <div class="pdc-card">
            <div class="pdc-header"><i class="bi bi-credit-card me-1"></i> PDC <?= (int)$pdc['pdc_number'] ?></div>

            <?php if ($pdc['file_path']): ?>
                <?php $isPdf = strtolower(pathinfo($pdc['original_filename'] ?? '', PATHINFO_EXTENSION)) === 'pdf'; ?>
                <?php if ($isPdf): ?>
                    <div style="background:#f0f6ff;border-radius:8px;padding:14px;text-align:center;margin-bottom:10px;">
                        <i class="bi bi-file-pdf" style="font-size:28px;color:#E87722;"></i>
                        <div style="font-size:11px;color:#888;margin-top:4px;"><?= h(basename($pdc['original_filename'])) ?></div>
                        <a href="<?= h($pdc['file_path']) ?>" target="_blank" style="font-size:11px;color:#0F4C81;">View PDF</a>
                    </div>
                <?php else: ?>
                    <img class="pdc-img mb-2"
                         src="<?= h($pdc['file_path']) ?>"
                         alt="PDC <?= (int)$pdc['pdc_number'] ?>"
                         onclick="window.open('<?= h($pdc['file_path']) ?>','_blank')">
                <?php endif; ?>
            <?php else: ?>
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;text-align:center;margin-bottom:10px;color:#aaa;font-size:12px;">
                    <i class="bi bi-image" style="font-size:24px;display:block;margin-bottom:4px;"></i>No image uploaded
                </div>
            <?php endif; ?>

            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <span class="badge-date"><?= h(date('M d, Y', strtotime($pdc['check_date']))) ?></span>
                <span class="badge-amt"><?= peso((float)$pdc['amount']) ?></span>
            </div>
            <div style="margin-top:6px;">
                <span class="badge-adj"><i class="bi bi-calendar-check me-1"></i>End of Month: <?= h(date('M d, Y', strtotime($pdc['adjusted_date']))) ?></span>
            </div>
            <div style="margin-top:8px;font-size:12px;">
                <?php if ($pdc['is_paid']): ?>
                    <span class="status-paid"><i class="bi bi-check-circle-fill"></i> Paid</span>
                <?php else: ?>
                    <span class="status-pending"><i class="bi bi-clock"></i> Pending</span>
                <?php endif; ?>
                <?php if ($pdc['is_notified']): ?>
                    &nbsp;<span style="color:#E87722;font-size:11px;"><i class="bi bi-bell-fill"></i> Notified</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Quick peso formatter for display
function peso(n) { return '₱' + parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2}); }
</script>
</body>
</html>
