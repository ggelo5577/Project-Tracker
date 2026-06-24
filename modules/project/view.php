<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
requireLogin();

$db        = getDB();
$projectId = (int)($_GET['id'] ?? 0);

if (!$projectId) { header('Location: '.appPath('index.php')); exit; }

$stmt = $db->prepare("
    SELECT p.*, f.firm_name, f.contact_person, f.contact_email, f.contact_phone
    FROM projects p
    JOIN firms f ON f.id = p.firm_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: '.appPath('index.php')); exit; }

// All submissions
$subStmt = $db->prepare("
    SELECT s.*, u.full_name AS submitted_by_name
    FROM submissions s
    LEFT JOIN users u ON u.id = s.submitted_by
    WHERE s.project_id = :pid
    ORDER BY s.stage, s.submitted_at DESC
");
$subStmt->execute([':pid' => $projectId]);
$allSubs = $subStmt->fetchAll();

// Group by stage
$subsByStage = [];
foreach ($allSubs as $sub) {
    $subsByStage[$sub['stage']][$sub['document_type']] = $sub;
}

// PDC count & refund summary
$pdcStmt = $db->prepare("SELECT COUNT(*) AS cnt, SUM(amount) AS total FROM pdcs WHERE project_id = :pid");
$pdcStmt->execute([':pid' => $projectId]);
$pdcInfo = $pdcStmt->fetch();

$refStmt = $db->prepare("
    SELECT COUNT(*) AS total, SUM(is_done) AS paid,
           SUM(refund_amount) AS total_amount,
           SUM(CASE WHEN is_done=1 THEN refund_amount ELSE 0 END) AS paid_amount
    FROM refund_schedule WHERE project_id = :pid
");
$refStmt->execute([':pid' => $projectId]);
$refInfo = $refStmt->fetch();

$stageLabels = [
    'approval'       => 'Approval Stage',
    'first_untagging'=> '1st Untagging Stage',
    'final_untagging'=> 'Final Untagging Stage',
    'pre_refunding'  => 'Pre-Refunding Submissions',
    'refunding'      => 'Refunding Stage',
    'completed'      => 'Completed',
];

$stageBadge = [
    'approval'       => 'approval',
    'first_untagging'=> 'first-unt',
    'final_untagging'=> 'final-unt',
    'pre_refunding'  => 'pre-refund',
    'refunding'      => 'refunding',
    'completed'      => 'completed',
];

$stageOrder = ['approval','first_untagging','final_untagging','pre_refunding','refunding','completed'];
$currentIdx = array_search($project['current_stage'], $stageOrder);

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

$stageNextUrl = [
    'approval'       => appPath('modules/project/stage_first_untagging.php'),
    'first_untagging'=> appPath('modules/project/stage_first_untagging.php'),
    'final_untagging'=> appPath('modules/project/stage_final_untagging.php'),
    'pre_refunding'  => appPath('modules/project/stage_pre_refunding.php'),
    'refunding'      => appPath('modules/project/stage_refunding.php'),
];

$pageTitle  = h($project['project_title']) . ' — Project Details';
$activePage = 'progress';
$breadcrumb = '<a href="'.h(appPath('index.php')).'">Dashboard</a> / <a href="'.h(appPath('modules/progress/index.php')).'">Progress</a> / ' . h($project['project_title']);
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-folder-fill me-2"></i> <?= h($project['project_title']) ?>
    </div>

    <!-- Stage Timeline -->
    <div class="stage-timeline mb-4">
        <?php foreach ($stageOrder as $i => $s): ?>
        <div class="stage-step">
            <div class="stage-label <?= $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'active' : '') ?>">
                <?= h($stageLabels[$s] ?? $s) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Left: Project Info -->
        <div class="col-lg-4">
            <!-- Project Image -->
            <?php if ($project['project_image']): ?>
            <div class="card mb-4">
                <div class="card-header-ppmis"><i class="bi bi-image me-2"></i>Project Image</div>
                <div class="card-body-ppmis" style="padding:12px;">
                    <img src="<?= h($project['project_image']) ?>" alt="Project Image"
                         style="width:100%;border-radius:10px;cursor:pointer;"
                         onclick="previewFile('<?= h($project['project_image']) ?>','Project Image')">
                </div>
            </div>
            <?php endif; ?>

            <!-- Project Info -->
            <div class="card mb-4">
                <div class="card-header-ppmis"><i class="bi bi-info-circle me-2"></i>Project Information</div>
                <div class="card-body-ppmis">
                    <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;white-space:nowrap;padding-right:12px;">Proponent</td>
                            <td style="padding:7px 0;font-weight:700;"><?= h($project['firm_name']) ?></td></tr>
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;border-top:1px solid #f0f0f0;">Contact</td>
                            <td style="padding:7px 0;border-top:1px solid #f0f0f0;"><?= h($project['contact_person'] ?? '—') ?></td></tr>
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;border-top:1px solid #f0f0f0;">Email</td>
                            <td style="padding:7px 0;border-top:1px solid #f0f0f0;"><?= h($project['contact_email'] ?? '—') ?></td></tr>
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;border-top:1px solid #f0f0f0;">Fund Amount</td>
                            <td style="padding:7px 0;border-top:1px solid #f0f0f0;font-weight:800;color:#0F4C81;font-size:15px;"><?= peso((float)$project['fund_amount']) ?></td></tr>
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;border-top:1px solid #f0f0f0;">Current Stage</td>
                            <td style="padding:7px 0;border-top:1px solid #f0f0f0;">
                                <span class="badge-stage <?= h($stageBadge[$project['current_stage']] ?? '') ?>">
                                    <?= h($stageLabels[$project['current_stage']] ?? $project['current_stage']) ?>
                                </span>
                            </td></tr>
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;border-top:1px solid #f0f0f0;">PDCs</td>
                            <td style="padding:7px 0;border-top:1px solid #f0f0f0;"><?= (int)$pdcInfo['cnt'] ?> submitted</td></tr>
                        <tr><td style="padding:7px 0;color:#888;font-weight:600;border-top:1px solid #f0f0f0;">Created</td>
                            <td style="padding:7px 0;border-top:1px solid #f0f0f0;"><?= h(date('M d, Y', strtotime($project['created_at']))) ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Refund Summary (if applicable) -->
            <?php if ((int)$refInfo['total'] > 0): ?>
            <div class="card mb-4">
                <div class="card-header-ppmis"><i class="bi bi-cash-coin me-2"></i>Refund Progress</div>
                <div class="card-body-ppmis">
                    <?php
                    $pct = (int)$refInfo['total'] > 0 ? round(((int)$refInfo['paid'] / (int)$refInfo['total']) * 100) : 0;
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;margin-bottom:4px;">
                            <span><?= (int)$refInfo['paid'] ?>/<?= (int)$refInfo['total'] ?> Paid</span>
                            <span style="color:#0F4C81;"><?= $pct ?>%</span>
                        </div>
                        <div style="background:#e9ecef;border-radius:20px;height:10px;">
                            <div style="width:<?= $pct ?>%;background:linear-gradient(90deg,#00C896,#009970);border-radius:20px;height:10px;transition:width 0.4s;"></div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px;">
                        <div style="background:#f0fdf9;border-radius:8px;padding:10px;text-align:center;">
                            <div style="font-weight:800;color:#00C896;font-size:16px;"><?= peso((float)$refInfo['paid_amount']) ?></div>
                            <div style="color:#888;font-size:11px;text-transform:uppercase;">Paid</div>
                        </div>
                        <div style="background:#fff9f5;border-radius:8px;padding:10px;text-align:center;">
                            <div style="font-weight:800;color:#E87722;font-size:16px;"><?= peso((float)$refInfo['total_amount'] - (float)$refInfo['paid_amount']) ?></div>
                            <div style="color:#888;font-size:11px;text-transform:uppercase;">Remaining</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <?php if ($project['current_stage'] !== 'completed'): ?>
            <div class="card">
                <div class="card-header-ppmis"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
                <div class="card-body-ppmis" style="display:flex;flex-direction:column;gap:10px;">
                    <a href="<?= h($stageNextUrl[$project['current_stage']] ?? '#') ?>?id=<?= $projectId ?>"
                       class="btn-ppmis-primary" style="justify-content:center;">
                        <i class="bi bi-pencil-square"></i> Continue This Stage
                    </a>
                    <a href="<?= h(appPath('modules/financial/report.php?id=' . $projectId)) ?>"
                       class="btn-ppmis-secondary" style="justify-content:center;">
                        <i class="bi bi-file-earmark-bar-graph"></i> Financial Report
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body-ppmis" style="text-align:center;padding:20px;">
                    <i class="bi bi-trophy-fill" style="font-size:36px;color:#E87722;display:block;margin-bottom:8px;"></i>
                    <div style="font-weight:700;color:#0F4C81;font-size:15px;">Project Completed!</div>
                    <a href="<?= h(appPath('modules/financial/report.php?id=' . $projectId)) ?>" class="btn-ppmis-primary" style="margin-top:12px;display:inline-flex;">
                        <i class="bi bi-file-earmark-bar-graph"></i> View Report
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Submissions by Stage -->
        <div class="col-lg-8">
            <?php
            $stagesWithDocs = [
                'approval'       => ['ppis_letter'],
                'first_untagging'=> ['release_letter','revised_annex_d','ipo','acknowledgement','cert_first_untagging'],
                'final_untagging'=> ['original_receipt','matrix_of_inspection','cert_final_untagging'],
                'pre_refunding'  => ['certified_true_copy','audited_financial_report'],
            ];
            foreach ($stagesWithDocs as $stage => $docTypes):
                $stageIdx = array_search($stage, $stageOrder);
                $isReached = $stageIdx <= $currentIdx;
            ?>
            <div class="card mb-4" style="<?= !$isReached ? 'opacity:0.5;' : '' ?>">
                <div class="card-header-ppmis" style="background:<?= $stageIdx < $currentIdx ? '#00C896' : ($stageIdx === $currentIdx ? '#0F4C81' : '#b0bec5') ?>">
                    <?php if ($stageIdx < $currentIdx): ?>
                        <i class="bi bi-check-circle-fill me-2"></i>
                    <?php elseif ($stageIdx === $currentIdx): ?>
                        <i class="bi bi-arrow-right-circle me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-lock me-2"></i>
                    <?php endif; ?>
                    <?= h($stageLabels[$stage]) ?>
                    <?php if ($stageIdx < $currentIdx): ?>
                        <span style="margin-left:auto;font-size:11px;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:10px;">Completed</span>
                    <?php endif; ?>
                </div>
                <div class="card-body-ppmis" style="padding:12px 16px;">
                    <?php if ($stage === 'first_untagging' && (int)$pdcInfo['cnt'] > 0): ?>
                    <div class="doc-row has-file" style="margin-bottom:8px;">
                        <i class="bi bi-file-earmark-check doc-status-icon" style="display:block;"></i>
                        <span class="doc-name">PDCs (<?= (int)$pdcInfo['cnt'] ?> submitted — <?= peso((float)$pdcInfo['total']) ?>)</span>
                        <div class="doc-actions">
                            <a href="<?= h(appPath('api/get_pdcs.php?project_id=' . $projectId)) ?>" target="_blank" class="btn-preview">
                                <i class="bi bi-eye"></i> View PDCs
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($docTypes as $dt):
                        $sub = $subsByStage[$stage][$dt] ?? null;
                    ?>
                    <div class="doc-row <?= $sub ? 'has-file' : '' ?>" style="margin-bottom:8px;">
                        <i class="bi bi-file-earmark-check doc-status-icon"></i>
                        <span class="doc-name"><?= h($docLabels[$dt] ?? $dt) ?></span>
                        <div class="doc-actions">
                            <?php if ($sub): ?>
                                <button class="btn-preview" onclick="previewFile('<?= h($sub['file_path']) ?>','<?= h($sub['original_filename']) ?>')">
                                    <i class="bi bi-eye"></i> Preview
                                </button>
                            <?php else: ?>
                                <span style="font-size:11px;color:#aaa;font-style:italic;">Not yet submitted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($isReached && !empty($docTypes)): ?>
                    <div style="margin-top:8px;">
                        <?php $submitted = 0;
                        foreach ($docTypes as $dt) { if (isset($subsByStage[$stage][$dt])) $submitted++; }
                        $total_docs = count($docTypes) + ($stage === 'first_untagging' ? 1 : 0); // +1 for PDC
                        $total_docs_check = $stage === 'first_untagging' ? count($docTypes) + ((int)$pdcInfo['cnt'] > 0 ? 1 : 0) : count($docTypes);
                        $total_submitted = $submitted + ($stage === 'first_untagging' && (int)$pdcInfo['cnt'] > 0 ? 1 : 0);
                        ?>
                        <div style="font-size:11px;color:#888;text-align:right;">
                            <?= $total_submitted ?>/<?= $total_docs_check ?> documents submitted
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>
