<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
requireLogin();

$db = getDB();

// Stats
$statsQuery = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active')    AS active,
        SUM(status = 'finished')  AS finished,
        SUM(status = 'refund')    AS refund,
        SUM(status = 'completed') AS completed
    FROM projects
");
$stats = $statsQuery->fetch();

// Refund stats
$refundQuery = $db->query("
    SELECT
        SUM(is_done = 1)  AS paid_count,
        SUM(is_done = 0)  AS pending_count,
        SUM(refund_amount) AS total_amount,
        SUM(CASE WHEN is_done = 1 THEN refund_amount ELSE 0 END) AS paid_amount
    FROM refund_schedule
");
$refundStats = $refundQuery->fetch();
$totalRefunds   = (int)($refundStats['paid_count'] ?? 0) + (int)($refundStats['pending_count'] ?? 0);
$paidPct    = $totalRefunds > 0 ? round(($refundStats['paid_count'] / $totalRefunds) * 100) : 0;
$pendingPct = 100 - $paidPct;

// Project status list (most recent 20)
$projectList = $db->query("
    SELECT p.id, p.project_title, p.current_stage, p.status,
           f.firm_name
    FROM projects p
    JOIN firms f ON f.id = p.firm_id
    ORDER BY p.updated_at DESC
    LIMIT 20
")->fetchAll();

$stageLabels = [
    'approval'       => 'Approval Stage',
    'first_untagging'=> '1st Untagging Stage',
    'final_untagging'=> 'Final Untagging Stage',
    'pre_refunding'  => 'Pre-Refunding',
    'refunding'      => 'Refund Stage',
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

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$breadcrumb = '<i class="bi bi-speedometer2 me-1"></i> Dashboard';
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-speedometer2 me-2"></i> DASHBOARD
    </div>

    <!-- Stat Cards -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
            <div>
                <div class="stat-label">Active Projects</div>
                <div class="stat-value"><?= h($stats['active'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="stat-label">Finished Projects</div>
                <div class="stat-value"><?= h($stats['finished'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="stat-label">Projects in Refund Stage</div>
                <div class="stat-value"><?= h($stats['refund'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gray"><i class="bi bi-trophy"></i></div>
            <div>
                <div class="stat-label">Completed Refunds</div>
                <div class="stat-value"><?= h($stats['completed'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Chart + Project Table Row -->
    <div class="row g-4">
        <!-- Refund Doughnut -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header-ppmis">
                    <i class="bi bi-pie-chart-fill"></i> Refund Summary
                </div>
                <div class="card-body-ppmis d-flex flex-column align-items-center justify-content-center" style="padding:32px 20px;">
                    <div class="chart-wrapper" style="width:220px;height:220px;">
                        <canvas id="refundChart"></canvas>
                    </div>
                    <div class="d-flex gap-20 mt-3" style="gap:20px;">
                        <div class="text-center">
                            <div style="font-size:22px;font-weight:800;color:#0F4C81;"><?= $paidPct ?>%</div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Paid</div>
                        </div>
                        <div style="width:1px;background:#dde3ea;"></div>
                        <div class="text-center">
                            <div style="font-size:22px;font-weight:800;color:#E87722;"><?= $pendingPct ?>%</div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Pending</div>
                        </div>
                    </div>
                    <?php if ($refundStats['total_amount'] > 0): ?>
                    <div style="margin-top:12px;font-size:12px;color:#888;">
                        Total: <strong><?= peso((float)$refundStats['total_amount']) ?></strong>
                        &nbsp;|&nbsp; Paid: <strong style="color:#00C896;"><?= peso((float)($refundStats['paid_amount'] ?? 0)) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Project Status Table -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header-ppmis">
                    <i class="bi bi-list-check"></i> Project Status
                </div>
                <div style="overflow-x:auto;">
                    <table class="ppmis-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Proponent</th>
                                <th>Stage</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($projectList)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:32px;color:#888;">
                                    <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                    No projects yet. <a href="<?= h(appPath('modules/project/create.php')) ?>">Start one!</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($projectList as $p): ?>
                            <tr>
                                <td><strong><?= h($p['project_title']) ?></strong></td>
                                <td><?= h($p['firm_name']) ?></td>
                                <td>
                                    <span class="badge-stage <?= h($stageBadge[$p['current_stage']] ?? '') ?>">
                                        <?= h($stageLabels[$p['current_stage']] ?? $p['current_stage']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= h(appPath('modules/project/view.php?id=' . (int)$p['id'])) ?>"
                                       class="btn-preview" style="font-size:12px;padding:5px 10px;border-radius:6px;text-decoration:none;">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
initRefundChart(<?= $paidPct ?>, <?= $pendingPct ?>);
</script>

<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/includes/header.php';
echo $pageContent;
require_once __DIR__ . '/includes/footer.php';
?>
