<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);

if (!$projectId) { header('Location: '.appPath('index.php')); exit; }

$stmt = $db->prepare("
    SELECT p.*, f.firm_name, f.contact_person, f.contact_email
    FROM projects p JOIN firms f ON f.id = p.firm_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: '.appPath('index.php')); exit; }

// Get audited financial report submission
$reportStmt = $db->prepare("
    SELECT * FROM submissions
    WHERE project_id = :pid AND document_type = 'audited_financial_report'
    ORDER BY submitted_at DESC LIMIT 1
");
$reportStmt->execute([':pid' => $projectId]);
$report = $reportStmt->fetch();

// Full submissions list
$subStmt = $db->prepare("
    SELECT s.*, u.full_name AS submitted_by_name
    FROM submissions s
    LEFT JOIN users u ON u.id = s.submitted_by
    WHERE s.project_id = :pid
    ORDER BY s.submitted_at DESC
");
$subStmt->execute([':pid' => $projectId]);
$allSubs = $subStmt->fetchAll();

// PDC / refund summary
$pdcStmt = $db->prepare("SELECT COUNT(*) AS cnt, SUM(amount) AS total FROM pdcs WHERE project_id = :pid");
$pdcStmt->execute([':pid' => $projectId]);
$pdcInfo = $pdcStmt->fetch();

$refStmt = $db->prepare("
    SELECT COUNT(*) AS total, SUM(is_done) AS paid,
           SUM(refund_amount) AS total_amt,
           SUM(CASE WHEN is_done=1 THEN refund_amount ELSE 0 END) AS paid_amt
    FROM refund_schedule WHERE project_id = :pid
");
$refStmt->execute([':pid' => $projectId]);
$refInfo = $refStmt->fetch();

$docLabels = [
    'ppis_letter'            => 'PPIS Letter',
    'release_letter'         => 'Release Letter',
    'pdc'                    => 'PDC',
    'revised_annex_d'        => 'Revised Annex D',
    'ipo'                    => 'IPO Document',
    'acknowledgement'        => 'Acknowledgement',
    'cert_first_untagging'   => 'Certificate of 1st Untagging',
    'original_receipt'       => 'Original Copy of Receipt',
    'matrix_of_inspection'   => 'Matrix of Inspection',
    'cert_final_untagging'   => 'Certificate of Final Untagging',
    'certified_true_copy'    => 'Certified True Copy of Receipt',
    'audited_financial_report'=> 'Audited Financial Report',
];

$stageLabels = [
    'approval'       => 'Approval Stage',
    'first_untagging'=> '1st Untagging Stage',
    'final_untagging'=> 'Final Untagging Stage',
    'pre_refunding'  => 'Pre-Refunding Submissions',
    'refunding'      => 'Refunding Stage',
    'completed'      => 'Completed',
];

$pageTitle  = 'Financial Report — ' . h($project['project_title']);
$activePage = 'progress';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / <a href="'.h(appPath('modules/progress/index.php')).'">Progress</a> / Financial Report';
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-file-earmark-bar-graph me-2"></i> Financial Report
    </div>

    <div class="row g-4">
        <!-- Main Report / Image Preview -->
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header-ppmis">
                    <i class="bi bi-image me-2"></i>
                    <?= h($project['project_title']) ?> — Audited Financial Report
                </div>
                <div class="card-body-ppmis">
                    <div class="financial-report-img" id="reportPreviewArea">
                        <?php if ($report && $report['file_path']): ?>
                            <?php
                            $isPdf = strtolower(pathinfo($report['original_filename'], PATHINFO_EXTENSION)) === 'pdf';
                            if ($isPdf): ?>
                                <embed src="<?= h($report['file_path']) ?>" type="application/pdf"
                                       width="100%" style="height:500px;border-radius:10px;">
                            <?php else: ?>
                                <img src="<?= h($report['file_path']) ?>"
                                     alt="Financial Report"
                                     style="cursor:pointer;"
                                     onclick="previewFile('<?= h($report['file_path']) ?>','<?= h($report['original_filename']) ?>')">
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="text-align:center;padding:60px 20px;color:#888;">
                                <i class="bi bi-file-earmark-x" style="font-size:48px;display:block;margin-bottom:12px;color:#dde3ea;"></i>
                                <div style="font-size:14px;font-weight:600;">No Audited Financial Report submitted yet</div>
                                
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($report): ?>
                    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                        <button class="btn-ppmis-primary" onclick="previewFile('<?= h($report['file_path']) ?>','<?= h($report['original_filename']) ?>')">
                            <i class="bi bi-zoom-in"></i> Full Preview
                        </button>
                        <a href="<?= h($report['file_path']) ?>" download class="btn-ppmis-secondary">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                    <div style="font-size:12px;color:#888;margin-top:8px;">
                        File: <strong><?= h($report['original_filename']) ?></strong>
                        — Submitted: <?= h(date('M d, Y g:i A', strtotime($report['submitted_at']))) ?>
                        <?php if ($report['submitted_by_name']): ?>
                        by <strong><?= h($report['submitted_by_name']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Submitted Documents -->
            <div class="card">
                <div class="card-header-ppmis"><i class="bi bi-folder-fill me-2"></i>All Submitted Documents</div>
                <div style="overflow-x:auto;">
                    <table class="ppmis-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Stage</th>
                                <th>Submitted</th>
                                <th>By</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($allSubs)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa;">No documents submitted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allSubs as $sub): ?>
                            <tr>
                                <td><i class="bi bi-file-earmark me-2" style="color:#E87722;"></i><?= h($docLabels[$sub['document_type']] ?? $sub['document_type']) ?></td>
                                <td><span style="font-size:11px;color:#888;"><?= h($stageLabels[$sub['stage']] ?? $sub['stage']) ?></span></td>
                                <td style="font-size:12px;white-space:nowrap;"><?= h(date('M d, Y', strtotime($sub['submitted_at']))) ?></td>
                                <td style="font-size:12px;"><?= h($sub['submitted_by_name'] ?? '—') ?></td>
                                <td>
                                    <button class="btn-preview" style="font-size:11px;padding:4px 10px;"
                                            onclick="previewFile('<?= h($sub['file_path']) ?>','<?= h($sub['original_filename']) ?>')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Summary Panel -->
        <div class="col-lg-5">
            <!-- Project Summary -->
            <div class="card mb-4">
                <div class="card-header-ppmis"><i class="bi bi-info-circle me-2"></i>Project Summary</div>
                <div class="card-body-ppmis">
                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <tr>
                            <td style="padding:8px 0;color:#888;font-weight:600;padding-right:14px;">Proponent</td>
                            <td style="padding:8px 0;font-weight:700;"><?= h($project['firm_name']) ?></td>
                        </tr>
                        <tr style="border-top:1px solid #f0f0f0;">
                            <td style="padding:8px 0;color:#888;font-weight:600;">Fund Amount</td>
                            <td style="padding:8px 0;font-weight:800;color:#0F4C81;font-size:16px;"><?= peso((float)$project['fund_amount']) ?></td>
                        </tr>
                        <tr style="border-top:1px solid #f0f0f0;">
                            <td style="padding:8px 0;color:#888;font-weight:600;">Current Stage</td>
                            <td style="padding:8px 0;"><span class="badge-stage refunding"><?= h($stageLabels[$project['current_stage']] ?? $project['current_stage']) ?></span></td>
                        </tr>
                        <tr style="border-top:1px solid #f0f0f0;">
                            <td style="padding:8px 0;color:#888;font-weight:600;">PDCs</td>
                            <td style="padding:8px 0;"><?= (int)$pdcInfo['cnt'] ?> checks — <?= peso((float)($pdcInfo['total'] ?? 0)) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Refund Statistics -->
            <?php if ((int)$refInfo['total'] > 0): ?>
            <div class="card mb-4">
                <div class="card-header-ppmis"><i class="bi bi-pie-chart me-2"></i>Refund Statistics</div>
                <div class="card-body-ppmis">
                    <?php
                    $pct = round(((int)$refInfo['paid'] / (int)$refInfo['total']) * 100);
                    ?>
                    <div style="margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:700;margin-bottom:6px;">
                            <span><?= (int)$refInfo['paid'] ?> of <?= (int)$refInfo['total'] ?> refunds paid</span>
                            <span style="color:#0F4C81;"><?= $pct ?>%</span>
                        </div>
                        <div style="background:#e9ecef;border-radius:20px;height:12px;">
                            <div style="width:<?= $pct ?>%;background:linear-gradient(90deg,#00C896,#009970);border-radius:20px;height:12px;"></div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div style="background:#f0fdf9;border-radius:10px;padding:12px;text-align:center;">
                                <div style="font-size:18px;font-weight:800;color:#00C896;"><?= peso((float)$refInfo['paid_amt']) ?></div>
                                <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Paid</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="background:#fff9f5;border-radius:10px;padding:12px;text-align:center;">
                                <div style="font-size:18px;font-weight:800;color:#E87722;"><?= peso((float)$refInfo['total_amt'] - (float)$refInfo['paid_amt']) ?></div>
                                <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Remaining</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div style="background:#f0f6ff;border-radius:10px;padding:12px;text-align:center;">
                                <div style="font-size:18px;font-weight:800;color:#0F4C81;"><?= peso((float)$refInfo['total_amt']) ?></div>
                                <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Total Refund Amount</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Refund Schedule Table -->
            <?php
            $schedStmt = $db->prepare("SELECT * FROM refund_schedule WHERE project_id=:pid ORDER BY refund_date LIMIT 12");
            $schedStmt->execute([':pid' => $projectId]);
            $schedule = $schedStmt->fetchAll();
            if (!empty($schedule)):
            ?>
            <div class="card">
                <div class="card-header-ppmis"><i class="bi bi-calendar-check me-2"></i>Refund Schedule (Recent)</div>
                <div style="overflow-x:auto;max-height:300px;overflow-y:auto;">
                    <table class="ppmis-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($schedule as $i => $r): ?>
                        <tr style="<?= $r['is_done'] ? 'opacity:0.65;' : '' ?>">
                            <td style="font-size:12px;color:#888;"><?= $i+1 ?></td>
                            <td style="font-size:12px;white-space:nowrap;"><?= h(date('M d, Y', strtotime($r['refund_date']))) ?></td>
                            <td style="font-weight:700;color:#0F4C81;"><?= peso((float)$r['refund_amount']) ?></td>
                            <td>
                                <?php if ($r['is_done']): ?>
                                    <span style="color:#00C896;font-size:12px;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Paid</span>
                                <?php elseif ($r['is_notified']): ?>
                                    <span style="color:#E87722;font-size:12px;font-weight:700;"><i class="bi bi-bell-fill"></i> Notified</span>
                                <?php else: ?>
                                    <span style="color:#aaa;font-size:12px;">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ((int)$refInfo['total'] > 12): ?>
                <div style="padding:8px 16px;font-size:12px;color:#888;border-top:1px solid #f0f0f0;">
                    Showing 12 of <?= (int)$refInfo['total'] ?> entries.
                    <a href="<?= h(appPath('modules/project/stage_refunding.php?id=' . $projectId)) ?>">View all →</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>
