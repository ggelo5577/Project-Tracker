<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
requireLogin();

$db = getDB();

// Load all projects with proponent & refund stats
$projects = $db->query("
    SELECT
        p.id,
        p.project_title,
        p.current_stage,
        p.fund_amount,
        f.firm_name,
        COALESCE(rs.total_refunds, 0) AS total_refunds,
        COALESCE(rs.paid_refunds, 0)  AS paid_refunds
    FROM projects p
    JOIN firms f ON f.id = p.firm_id
    LEFT JOIN (
        SELECT project_id,
               COUNT(*) AS total_refunds,
               SUM(is_done) AS paid_refunds
        FROM refund_schedule
        GROUP BY project_id
    ) rs ON rs.project_id = p.id
    ORDER BY p.updated_at DESC
")->fetchAll();

$stageLabels = [
    'approval'       => 'Approval',
    'first_untagging' => '1st Untagging',
    'final_untagging' => 'Final Untagging',
    'pre_refunding'  => 'Pre-Refunding',
    'refunding'      => 'Refunding',
    'completed'      => 'Completed',
];

$stageBadge = [
    'approval'       => 'approval',
    'first_untagging' => 'first-unt',
    'final_untagging' => 'final-unt',
    'pre_refunding'  => 'pre-refund',
    'refunding'      => 'refunding',
    'completed'      => 'completed',
];

$stageUrl = [
    'approval'       => '/modules/project/create.php',
    'first_untagging' => '/modules/project/stage_first_untagging.php',
    'final_untagging' => '/modules/project/stage_final_untagging.php',
    'pre_refunding'  => '/modules/project/stage_pre_refunding.php',
    'refunding'      => '/modules/project/stage_refunding.php',
    'completed'      => '/modules/progress/view.php',
];

$stageNextUrl = [
    'approval'       => appPath('modules/project/stage_first_untagging.php'),
    'first_untagging' => appPath('modules/project/stage_first_untagging.php'),
    'final_untagging' => appPath('modules/project/stage_final_untagging.php'),
    'pre_refunding'  => appPath('modules/project/stage_pre_refunding.php'),
    'refunding'      => appPath('modules/project/stage_refunding.php'),
];

$pageTitle  = 'Progress Monitoring';
$activePage = 'progress';
$breadcrumb = '<a href="' . h(appPath('index.php')) . '">Dashboard</a> / Progress';
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-bar-chart-steps me-2"></i> Progress
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <i class="bi bi-search"></i>
        <input type="text" id="progressSearch" placeholder="Search proponent or project…." autocomplete="off">
    </div>

    <!-- Projects Table -->
    <div class="card">
        <div style="overflow-x:auto;">
            <table class="ppmis-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Proponent</th>
                        <th>Status</th>
                        <th>Refund Progress</th>
                        <th>Financial Report</th>
                        <th>Stage Status</th>
                    </tr>
                </thead>
                <tbody id="progressTableBody">
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;color:#888;">
                                <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                No projects found. <a href="<?= h(appPath('modules/project/create.php')) ?>">Create your first project.</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $p):
                            $stage     = $p['current_stage'];
                            $badgeCls  = $stageBadge[$stage] ?? '';
                            $stageText = $stageLabels[$stage] ?? $stage;
                            $total     = (int)$p['total_refunds'];
                            $paid      = (int)$p['paid_refunds'];
                            $refundPct = $total > 0 ? round(($paid / $total) * 100) : 0;
                            $isRefund  = in_array($stage, ['refunding', 'completed']);
                            $viewUrl   = ($stageUrl[$stage] ?? '/modules/progress/view.php') . '?id=' . (int)$p['id'];
                            $projectId       = $p['id'];
                        ?>
                            <tr>
                                <td><strong><?= h($p['project_title']) ?></strong></td>
                                <td><?= h($p['firm_name']) ?></td>
                                <td>
                                    <span class="badge-stage <?= h($badgeCls) ?>">
                                        <?= h($stageText) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isRefund && $total > 0): ?>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="flex:1;background:#e9ecef;border-radius:20px;height:8px;min-width:80px;">
                                                <div style="width:<?= $refundPct ?>%;background:#00C896;border-radius:20px;height:8px;transition:width 0.3s;"></div>
                                            </div>
                                            <span style="font-size:12px;font-weight:700;color:#0F4C81;"><?= $refundPct ?>%</span>
                                        </div>
                                        <div style="font-size:11px;color:#888;margin-top:2px;"><?= $paid ?>/<?= $total ?> paid</div>
                                    <?php else: ?>
                                        <span style="font-size:12px;color:#aaa;">Not Applicable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= h(appPath('modules/financial/report.php?id=' . (int)$projectId)) ?>"
                                        class="btn-preview" style="font-size:12px;padding:6px 12px;border-radius:6px;text-decoration:none;">
                                        <i class="bi bi-eye me-1"></i>View Financial Report
                                    </a>
                                </td>
                                <td>
                                    <div style="max-width:200px;">
                                        <?php if ($stage == 'terminated'): ?>
                                            <div class="btn-ppmis-disabled" style="justify-content:center;min-height: 5px;margin-bottom: 5px;">
                                                <i class="bi bi-ban"></i> Terminated Project
                                            </div>
                                        <?php else: ?>
                                            <a href="<?= h($stageNextUrl[$stage] ?? '#') ?>?id=<?= $projectId ?>"
                                                class="btn-ppmis-primary" style="justify-content:center;min-height: 5px;margin-bottom: 5px;">
                                                <i class="bi bi-pencil-square"></i> Continue This Stage
                                            </a>
                                        <?php endif; ?>
                                        <div style="display:flex;">
                                            <a href="<?= h(appPath('modules/progress/view.php?id=' . (int)$projectId)) ?>"
                                                class="btn-preview" style="font-size:12px;padding:6px 12px;border-radius:6px;text-decoration:none;margin: 5px;">
                                                <i class="bi bi-eye me-1"></i>View Status
                                            </a>
                                            <a href="<?= h(appPath('modules/progress/terminate.php?id=' . (int)$projectId)) ?>"
                                                class="btn-preview-danger" style="font-size:12px;padding:6px 12px;border-radius:6px;text-decoration:none;">
                                                <i class="bi bi-stop-circle"></i>Terminate Project
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
    initSearch('progressSearch', 'progressTableBody');
</script>