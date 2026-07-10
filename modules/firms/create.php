<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
requireLogin();

$db    = getDB();
$error = '';
$success = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrf();
    $firmName     = sanitize($_POST['firm_name'] ?? '');
    $contactPerson = sanitize($_POST['contact_person'] ?? '');
    $contactEmail  = filter_var(trim($_POST['contact_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
    $contactPhone  = sanitize($_POST['contact_phone'] ?? '');
    $address       = sanitize($_POST['address'] ?? '');

    if ($firmName === '') {
        $error = 'Firm name is required.';
    } else {
        // Check duplicate
        $chk = $db->prepare("SELECT id FROM firms WHERE firm_name = :n LIMIT 1");
        $chk->execute([':n' => $firmName]);
        if ($chk->fetch()) {
            $error = 'A firm with this name already exists.';
        } else {
            $ins = $db->prepare("INSERT INTO firms (firm_name, contact_person, contact_email, contact_phone, address, created_by)
                VALUES (:n,:cp,:ce,:ph,:addr,:uid)");
            $ins->execute([':n' => $firmName, ':cp' => $contactPerson, ':ce' => $contactEmail, ':ph' => $contactPhone, ':addr' => $address, ':uid' => currentUser()['id']]);
            logActivity(currentUser()['id'], null, 'CREATE_FIRM', "Created firm: $firmName");
            $success = "Firm \"$firmName\" added successfully!";
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verifyCsrf();
    $fid           = (int)($_POST['firm_id'] ?? 0);
    $firmName      = sanitize($_POST['firm_name'] ?? '');
    $contactPerson = sanitize($_POST['contact_person'] ?? '');
    $contactEmail  = filter_var(trim($_POST['contact_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
    $contactPhone  = sanitize($_POST['contact_phone'] ?? '');
    $address       = sanitize($_POST['address'] ?? '');

    if (!$fid) {
        $error = 'Invalid firm.';
    } elseif ($firmName === '') {
        $error = 'Firm name is required.';
    } else {
        // Check duplicate name on a different firm
        $chk = $db->prepare("SELECT id FROM firms WHERE firm_name = :n AND id != :id LIMIT 1");
        $chk->execute([':n' => $firmName, ':id' => $fid]);
        if ($chk->fetch()) {
            $error = 'A firm with this name already exists.';
        } else {
            $upd = $db->prepare("UPDATE firms SET firm_name = :n, contact_person = :cp, contact_email = :ce, contact_phone = :ph, address = :addr WHERE id = :id");
            $upd->execute([':n' => $firmName, ':cp' => $contactPerson, ':ce' => $contactEmail, ':ph' => $contactPhone, ':addr' => $address, ':id' => $fid]);
            logActivity(currentUser()['id'], null, 'UPDATE_FIRM', "Updated firm: $firmName");
            $success = "Firm \"$firmName\" updated successfully!";
        }
    }
}

// Handle toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    verifyCsrf();
    $fid = (int)($_POST['firm_id'] ?? 0);
    if ($fid) {
        $db->prepare("UPDATE firms SET is_active = NOT is_active WHERE id = :id")->execute([':id' => $fid]);
    }
    header('Location: ' . appPath('modules/firms/create.php'));
    exit;
}

$firms = $db->query("SELECT f.*, COUNT(p.id) AS project_count FROM firms f LEFT JOIN projects p ON p.firm_id=f.id GROUP BY f.id ORDER BY f.firm_name")->fetchAll();
$csrf  = csrfToken();

$pageTitle  = 'Firm Management';
$activePage = 'firms';
$breadcrumb = '<a href="' . h(appPath('index.php')) . '">Dashboard</a> / Firm Management';
ob_start();
?>
<div class="page-content">
    <div class="page-banner">
        <i class="bi bi-building me-2"></i> Firm / Proponent Management
    </div>

    <div class="row g-4">
        <!-- Add Firm Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header-ppmis"><i class="bi bi-plus-circle me-2"></i>Add New Proponent</div>
                <div class="card-body-ppmis">
                    <?php if ($error): ?>
                        <div class="alert-ppmis error mb-3"><i class="bi bi-x-circle"></i><?= h($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert-ppmis success mb-3"><i class="bi bi-check-circle"></i><?= h($success) ?></div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label class="form-label-ppmis">Firm / Organization Name *</label>
                            <input type="text" name="firm_name" class="ppmis-input"
                                value="<?= h($_POST['firm_name'] ?? '') ?>"
                                placeholder="e.g. TechCorp Solutions Inc." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-ppmis">Contact Person</label>
                            <input type="text" name="contact_person" class="ppmis-input"
                                value="<?= h($_POST['contact_person'] ?? '') ?>"
                                placeholder="Full name of contact">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-ppmis">Email Address</label>
                            <input type="email" name="contact_email" class="ppmis-input"
                                value="<?= h($_POST['contact_email'] ?? '') ?>"
                                placeholder="email@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-ppmis">Phone Number</label>
                            <input type="text" name="contact_phone" class="ppmis-input"
                                value="<?= h($_POST['contact_phone'] ?? '') ?>"
                                placeholder="09XX-XXX-XXXX">
                        </div>
                        <div class="mb-4">
                            <label class="form-label-ppmis">Address</label>
                            <textarea name="address" class="ppmis-input" rows="2"
                                placeholder="Office/mailing address"><?= h($_POST['address'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn-ppmis-primary" style="width:100%;justify-content:center;">
                            <i class="bi bi-plus-lg"></i> Add Proponent
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Firms List -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header-ppmis">
                    <i class="bi bi-list-ul me-2"></i>Registered Proponents
                    <span style="margin-left:auto;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:10px;font-size:12px;">
                        <?= count($firms) ?> total
                    </span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ppmis-table">
                        <thead>
                            <tr>
                                <th>Firm Name</th>
                                <th>Contact No.</th>
                                <th>Address</th>
                                <th>Projects</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($firms)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:32px;color:#aaa;">No firms yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($firms as $f): ?>
                                    <tr style="<?= !$f['is_active'] ? 'opacity:0.5;' : '' ?>">
                                        <td>
                                            <div style="font-weight:700;"><?= h($f['firm_name']) ?></div>
                                            <?php if ($f['contact_email']): ?>
                                                <div style="font-size:11px;color:#888;"><?= h($f['contact_email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:13px;">
                                            <?= h($f['contact_phone'] ?? '—') ?>
                                        </td>
                                        <td>
                                            <?php if ($f['address']): ?>
                                                <?= h($f['address']) ?>
                                            <?php else: ?>
                                                <p>No address added</p>
                                            <?php endif; ?>

                                        </td>
                                        <td>
                                            <span style="background:#e3f0ff;color:#0F4C81;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                                                <?= (int)$f['project_count'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size:12px;font-weight:700;color:<?= $f['is_active'] ? '#00C896' : '#aaa' ?>;">
                                                <i class="bi bi-circle-fill" style="font-size:8px;"></i>
                                                <?= $f['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="firm_id" value="<?= (int)$f['id'] ?>">
                                                    <button type="submit" class="btn-preview" style="font-size:11px;padding:4px 10px;width:100%;">
                                                        <?= $f['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>
                                                <?php if($f['is_active']): ?>
                                                    <button type="button" class="btn-preview" style="font-size:11px;padding:4px 10px;width:100%;"
                                                    onclick="openEditFirmModal(<?= (int)$f['id'] ?>, <?= htmlspecialchars(json_encode([
                                                        'firm_name' => $f['firm_name'],
                                                        'contact_person' => $f['contact_person'],
                                                        'contact_email' => $f['contact_email'],
                                                        'contact_phone' => $f['contact_phone'],
                                                        'address' => $f['address'],
                                                    ]), ENT_QUOTES, 'UTF-8') ?>)">
                                                    Edit
                                                </button>
                                                <?php else: ?>
                                                <?php endif; ?>
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
    </div>
</div>

<!-- Edit Firm Modal -->
<div id="editFirmModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1050;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,0.2);">
        <div class="card-header-ppmis" style="display:flex;align-items:center;justify-content:space-between;border-radius:10px 10px 0 0;">
            <span><i class="bi bi-pencil-square me-2"></i>Edit Proponent</span>
            <button type="button" onclick="closeEditFirmModal()" style="background:none;border:none;color:#fff;font-size:20px;line-height:1;cursor:pointer;">&times;</button>
        </div>
        <div class="card-body-ppmis" style="padding:20px;">
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="firm_id" id="edit_firm_id" value="">

                <div class="mb-3">
                    <label class="form-label-ppmis">Firm / Organization Name *</label>
                    <input type="text" name="firm_name" id="edit_firm_name" class="ppmis-input" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-ppmis">Contact Person</label>
                    <input type="text" name="contact_person" id="edit_contact_person" class="ppmis-input">
                </div>
                <div class="mb-3">
                    <label class="form-label-ppmis">Email Address</label>
                    <input type="email" name="contact_email" id="edit_contact_email" class="ppmis-input">
                </div>
                <div class="mb-3">
                    <label class="form-label-ppmis">Phone Number</label>
                    <input type="text" name="contact_phone" id="edit_contact_phone" class="ppmis-input">
                </div>
                <div class="mb-4">
                    <label class="form-label-ppmis">Address</label>
                    <textarea name="address" id="edit_address" class="ppmis-input" rows="2"></textarea>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn-preview" style="flex:1;justify-content:center;" onclick="closeEditFirmModal()">Cancel</button>
                    <button type="submit" class="btn-ppmis-primary" style="flex:1;justify-content:center;">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditFirmModal(id, data) {
    document.getElementById('edit_firm_id').value = id;
    document.getElementById('edit_firm_name').value = data.firm_name || '';
    document.getElementById('edit_contact_person').value = data.contact_person || '';
    document.getElementById('edit_contact_email').value = data.contact_email || '';
    document.getElementById('edit_contact_phone').value = data.contact_phone || '';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('editFirmModalOverlay').style.display = 'flex';
}
function closeEditFirmModal() {
    document.getElementById('editFirmModalOverlay').style.display = 'none';
}
document.getElementById('editFirmModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeEditFirmModal();
});
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../../includes/header.php';
echo $pageContent;
require_once __DIR__ . '/../../includes/footer.php';
?>