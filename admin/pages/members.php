<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();

$db = database();
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$statusFilter = in_array($statusFilter, ['all', 'active', 'inactive'], true) ? $statusFilter : 'all';

$where = ['archived_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = '(resident_number LIKE ? OR household_number LIKE ? OR head_name LIKE ? OR contact_number LIKE ? OR address LIKE ?)';
    for ($i = 0; $i < 5; $i++) $params[] = '%' . $search . '%';
}
if ($statusFilter === 'active') {
    $where[] = 'is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'is_active = 0';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStatement = $db->prepare("SELECT COUNT(*) FROM residents $whereSql");
$countStatement->execute($params);
$memberCount = (int) $countStatement->fetchColumn();
$pageCount = max(1, (int) ceil($memberCount / $perPage));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pageCount);
$offset = ($page - 1) * $perPage;

$memberStatement = $db->prepare(
    "SELECT id, resident_number, household_number, head_name, contact_number, address, is_active
     FROM residents
     $whereSql
     ORDER BY id DESC
     LIMIT $perPage OFFSET $offset"
);
$memberStatement->execute($params);
$members = $memberStatement->fetchAll();
$success = flash('member_success');
$error = flash('member_error');

$purokOptions = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];
$civilStatusOptions = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'divorced' => 'Divorced'];

function render_member_results(array $members, int $memberCount, int $perPage, string $search, string $statusFilter, int $page, int $pageCount): string
{
    ob_start();
    ?>
        <div class="card-title-row">
            <div>
                <div class="card-title">Registered Members</div>
                <div class="card-desc"><?=$memberCount?> resident account<?=$memberCount===1?'':'s'?> in the system<?= $memberCount > $perPage ? ' · Page ' . $page . ' of ' . $pageCount : '' ?><?= ($search !== '' || $statusFilter !== 'all') ? ' · Filtered' : '' ?>.</div>
            </div>
        </div>
        <table class="responsive-table member-table">
            <thead><tr><th>Account No.</th><th>Household No.</th><th>Head of Household</th><th>Contact</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$members): ?>
                <tr><td colspan="7" data-label=""><?= ($search !== '' || $statusFilter !== 'all') ? 'No members match your search or filter.' : 'No residents have been registered yet.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td data-label="Account No."><?=h($m['resident_number'])?></td>
                    <td data-label="Household No."><?=h($m['household_number'])?></td>
                    <td data-label="Head of Household"><?=h($m['head_name'])?></td>
                    <td data-label="Contact"><?=h($m['contact_number'])?></td>
                    <td data-label="Address"><?=h($m['address'])?></td>
                    <td data-label="Status"><span class="status-pill <?=$m['is_active']?'is-active':'is-inactive'?>"><?=$m['is_active']?'Active':'Deactivated'?></span></td>
                    <td data-label="Actions">
                        <div class="row-actions">
                            <a class="btn btn-outline btn-sm icon-action" href="resident_view.php?id=<?=(int) $m['id']?>" aria-label="View full record" data-tooltip="View / Edit full record"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                            <?php
                                $resetConfirm = json_encode([
                                    'title' => 'Reset this member\'s password?',
                                    'description' => 'A new temporary password will be generated for ' . $m['head_name'] . '. They will need to use it to log in again.',
                                    'confirmLabel' => 'Yes, reset password',
                                    'variant' => 'danger',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                $toggleConfirm = json_encode([
                                    'title' => ($m['is_active'] ? 'Deactivate' : 'Activate') . ' this member account?',
                                    'description' => $m['is_active']
                                        ? $m['head_name'] . ' will no longer be able to log in or answer surveys until reactivated.'
                                        : $m['head_name'] . ' will be able to log in and answer surveys again.',
                                    'confirmLabel' => $m['is_active'] ? 'Yes, deactivate' : 'Yes, activate',
                                    'variant' => $m['is_active'] ? 'danger' : 'info',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <form action="../../process/update_member.php" method="post" data-confirm-modal='<?=$resetConfirm?>'>
                                <input type="hidden" name="resident_id" value="<?=(int) $m['id']?>">
                                <input type="hidden" name="action" value="reset_password">
                                <button class="btn btn-outline btn-sm icon-action" type="submit" aria-label="Reset password" data-tooltip="Reset password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3"/><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M12 14v2"/></svg></button>
                            </form>
                            <form action="../../process/update_member.php" method="post" data-confirm-modal='<?=$toggleConfirm?>'>
                                <input type="hidden" name="resident_id" value="<?=(int) $m['id']?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn btn-sm <?=$m['is_active']?'btn-danger':'btn-primary'?> icon-action" type="submit" aria-label="<?=$m['is_active']?'Deactivate':'Activate'?> member" data-tooltip="<?=$m['is_active']?'Deactivate':'Activate'?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v10"/><path d="M6.2 5.2a8 8 0 1 0 11.6 0"/></svg></button>
                            </form>
                            <?php
                                $archiveConfirm = json_encode([
                                    'title' => 'Move this resident record to the archive?',
                                    'description' => $m['head_name'] . '\'s record will be moved to the Archive and their account deactivated. You can restore it or delete it permanently from the Archive.',
                                    'confirmLabel' => 'Yes, move to archive',
                                    'variant' => 'danger',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <form action="../../process/admin_archive_resident.php" method="post" data-confirm-modal='<?=$archiveConfirm?>'>
                                <input type="hidden" name="resident_id" value="<?=(int) $m['id']?>">
                                <button class="btn btn-danger btn-sm icon-action" type="submit" aria-label="Archive resident record" data-tooltip="Move to archive"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v4H3z"/><path d="M5 9v10h14V9"/><path d="M10 13h4"/></svg></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($memberCount > 0): ?>
            <nav class="member-pagination" aria-label="Member pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" data-page="<?= $page - 1 ?>" href="members.php?page=<?= $page - 1 ?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?><a class="btn btn-outline" data-page="<?= $page + 1 ?>" href="members.php?page=<?= $page + 1 ?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}

$resultsHtml = render_member_results($members, $memberCount, $perPage, $search, $statusFilter, $page, $pageCount);

if ((($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'XMLHttpRequest') {
    header('Content-Type: text/html; charset=UTF-8');
    echo $resultsHtml;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Member Management</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css?v=3">
<link rel="stylesheet" href="../css/members.css?v=12">
<link rel="stylesheet" href="../../assets/css/profile.css?v=5">
<link rel="stylesheet" href="../css/confirm-modal.css?v=3">
</head>
<body>
<div class="portal-shell">
<div class="sidebar-backdrop"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
    <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?=h($admin['full_name'])?><small><?=h(ucfirst($admin['role']))?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
    <nav class="nav-group"><span class="nav-label">Management</span>
        <a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
        <a class="nav-link" href="surveys.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
        <a class="nav-link" href="members.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
        <a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a>
        <div class="nav-group-item">
            <button type="button" class="nav-link nav-parent" aria-expanded="false"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                <span>Reports</span>
                <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-submenu">
                <a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
                <a class="nav-link nav-sublink" href="member_reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a>
            </div>
        </div>
        <?php if ($admin['role'] === 'admin'): ?><a class="nav-link" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a><?php endif; ?>
    </nav>
    <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
</aside>
<main class="main">
    <div class="sticky-head">
    <div class="topbar">
        <div>
            <button class="menu-toggle">☰</button>
            <div class="page-eyebrow">Account administration</div>
            <h1 class="page-title">Member Management</h1>
            <p class="page-sub">Add, edit, activate, or deactivate registered resident accounts.</p>
        </div>
        <button class="btn btn-primary" type="button" onclick="openAddMemberModal()">+ Add Member</button>
    </div>
    <?php if ($success): ?><p class="notice notice-success" role="status"><?=h($success)?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?=h($error)?></p><?php endif; ?>

    <form class="member-filters card" method="get" id="memberFilterForm">
        <div class="member-filters-row">
            <label class="member-filter-field">
                <span class="member-filter-label">Search members</span>
                <input type="search" name="q" id="memberSearchInput" value="<?= h($search) ?>" placeholder="Search by name, account no., household no., contact, or address" autocomplete="off">
            </label>
            <label class="member-filter-field member-filter-field-status">
                <span class="member-filter-label">Status</span>
                <select name="status" id="memberStatusSelect">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
                </select>
            </label>
        </div>
    </form>
    </div>

    <div class="card members-list-card">
        <div id="memberResults"><?= $resultsHtml ?></div>
    </div>
</main>
</div>

<!-- Add Member Modal -->
<div class="modal-overlay" id="addMemberOverlay">
    <div class="modal-box">
        <div class="modal-head">
            <div><h2>Add Member</h2><p>A resident account number and temporary password will be generated automatically.</p></div>
            <button class="modal-close" type="button" onclick="closeAddMemberModal()">&times;</button>
        </div>
        <form action="../../process/create_member.php" method="post" enctype="multipart/form-data" id="addMemberForm">
            <details class="card accordion-card add-member-card" name="add-member-accordion" open>
                <summary><span class="accordion-icon">1</span><span class="accordion-heading"><span class="accordion-title">Personal Information</span><span class="accordion-subtitle">Account, address, contact, and personal details</span></span><span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span></summary>
                <div class="accordion-body"><div class="accordion-body-inner">
                    <input type="hidden" id="headName" name="head_name">
                    <div class="form-group"><label for="householdNo">Resident / Household Number <span class="optional">(optional)</span></label><input id="householdNo" name="household_number" type="text" placeholder="e.g. HH-0002"></div>
                    <div class="form-row-2"><div class="form-group"><label for="lastName">Last Name</label><input id="lastName" name="last_name" type="text" maxlength="80" required></div><div class="form-group"><label for="firstName">First Name</label><input id="firstName" name="first_name" type="text" maxlength="80" required></div></div>
                    <div class="form-row-2"><div class="form-group"><label for="middleName">Middle Name <span class="optional">(optional)</span></label><input id="middleName" name="middle_name" type="text" maxlength="80"></div><div class="form-group"><label for="extensionName">Extension Name <span class="optional">(optional)</span></label><input id="extensionName" name="extension_name" type="text" maxlength="20" placeholder="Jr., Sr., III"></div></div>
                    <div class="form-row-2"><div class="form-group"><label for="sex">Sex</label><select id="sex" name="sex"><option value="" selected>Select sex</option><option value="male">Male</option><option value="female">Female</option></select></div><div class="form-group"><label for="civilStatus">Civil Status</label><select id="civilStatus" name="civil_status" required><option value="" disabled selected>Select civil status</option><?php foreach ($civilStatusOptions as $value => $label): ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?></select></div></div>
                    <div class="form-row-2">
                        <div class="form-group"><label for="contactNo">Contact Number</label><input id="contactNo" name="contact_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" title="Enter an 11-digit contact number using numbers only" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" required></div>
                        <div class="form-group"><label for="purok">Purok <span class="optional">(optional)</span></label><select id="purok" name="purok"><option value="" selected>Select purok</option><?php foreach ($purokOptions as $purokValue): ?><option value="<?= h($purokValue) ?>"><?= h($purokValue) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="form-row-2"><div class="form-group"><label for="birthday">Birth of Date</label><input id="birthday" name="birthday" type="date" min="1900-01-01" max="<?= date('Y-m-d') ?>" required></div><div class="form-group"><label for="addAgeDisplay">Age</label><input id="addAgeDisplay" type="text" value="—" readonly></div></div>
                    <div class="form-group"><label for="address">Address</label><textarea id="address" name="address" rows="3" required></textarea></div>
                    <div class="form-row-2"><div class="form-group"><label for="occupation">Occupation <span class="optional">(optional)</span></label><input id="occupation" name="occupation" type="text" maxlength="120"></div><div class="form-group"><label for="employer">Employer <span class="optional">(optional)</span></label><input id="employer" name="employer" type="text" maxlength="120"></div></div>
                    <div class="form-group"><label for="employerAddress">Employer Address <span class="optional">(optional)</span></label><textarea id="employerAddress" name="employer_address" rows="2"></textarea></div>
                </div></div>
            </details>

            <details class="card accordion-card add-member-card" name="add-member-accordion"><summary><span class="accordion-icon">2</span><span class="accordion-heading"><span class="accordion-title">Spouse Information</span><span class="accordion-subtitle">Spouse name, occupation, and employer</span></span><span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="accordion-body"><div class="accordion-body-inner"><div class="form-group"><label for="addSpouseName">Spouse Name <span class="optional">(optional)</span></label><input id="addSpouseName" name="spouse_name" maxlength="120"></div><div class="form-row-2"><div class="form-group"><label for="addSpouseOccupation">Occupation <span class="optional">(optional)</span></label><input id="addSpouseOccupation" name="spouse_occupation" maxlength="120"></div><div class="form-group"><label for="addSpouseEmployer">Employer <span class="optional">(optional)</span></label><input id="addSpouseEmployer" name="spouse_employer" maxlength="120"></div></div></div></div></details>

            <details class="card accordion-card add-member-card" name="add-member-accordion"><summary><span class="accordion-icon">3</span><span class="accordion-heading"><span class="accordion-title">Parents Information</span><span class="accordion-subtitle">Father's and mother's name</span></span><span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="accordion-body"><div class="accordion-body-inner"><div class="form-row-2"><div class="form-group"><label for="addFatherName">Father's Name <span class="optional">(optional)</span></label><input id="addFatherName" name="father_name" maxlength="120"></div><div class="form-group"><label for="addMotherName">Mother's Name <span class="optional">(optional)</span></label><input id="addMotherName" name="mother_name" maxlength="120"></div></div></div></div></details>

            <details class="card accordion-card add-member-card" name="add-member-accordion"><summary><span class="accordion-icon">4</span><span class="accordion-heading"><span class="accordion-title">Children Information</span><span class="accordion-subtitle">Add children and their ages</span></span><span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="accordion-body"><div class="accordion-body-inner"><div id="addChildrenRows"></div><p class="repeat-empty" id="addChildrenEmpty">No children added yet.</p><button type="button" class="add-row-btn" id="addMemberChildRow">＋ Add a Child</button></div></div></details>

            <details class="card accordion-card add-member-card" name="add-member-accordion"><summary><span class="accordion-icon">5</span><span class="accordion-heading"><span class="accordion-title">Character References</span><span class="accordion-subtitle">Two reference names and optional signatures</span></span><span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="accordion-body"><div class="accordion-body-inner"><?php foreach ([1, 2] as $i): ?><div class="repeat-row"><div class="repeat-row-head"><span class="repeat-row-label">Reference <?= $i ?></span></div><div class="form-group"><label for="addReference<?= $i ?>">Full Name <span class="optional">(optional)</span></label><input id="addReference<?= $i ?>" name="reference_name_<?= $i ?>" maxlength="120"></div><div class="form-group"><label for="addSignature<?= $i ?>">Signature <span class="optional">(optional)</span></label><input id="addSignature<?= $i ?>" name="signature_<?= $i ?>" type="file" accept="image/png,image/jpeg,image/webp"></div></div><?php endforeach; ?></div></div></details>

            <details class="card accordion-card add-member-card" name="add-member-accordion"><summary><span class="accordion-icon">6</span><span class="accordion-heading"><span class="accordion-title">Profile Photo</span><span class="accordion-subtitle">Upload a JPG, PNG, or WEBP photo</span></span><span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="accordion-body"><div class="accordion-body-inner"><div class="form-group"><label for="addPhoto">Profile Photo <span class="optional">(optional, max 3MB)</span></label><input id="addPhoto" name="photo" type="file" accept="image/png,image/jpeg,image/webp"></div></div></div></details>

            <div class="modal-actions">
                <button class="btn btn-outline" type="button" onclick="closeAddMemberModal()">Cancel</button>
                <button class="btn btn-primary" type="submit">Add Member</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal-overlay" id="editMemberOverlay">
    <div class="modal-box">
        <div class="modal-head">
            <div><h2>Edit Member</h2><p>Update this resident's household details.</p></div>
            <button class="modal-close" type="button" onclick="closeEditMemberModal()">&times;</button>
        </div>
        <form action="../../process/update_member.php" method="post" id="editMemberForm">
            <input type="hidden" name="resident_id" id="editResidentId">
            <input type="hidden" name="action" value="save">
            <div class="form-group"><label for="editHouseholdNo">Household No.</label><input id="editHouseholdNo" name="household_number" type="text" required></div>
            <div class="form-group"><label for="editHeadName">Head of Household</label><input id="editHeadName" name="head_name" type="text" required></div>
            <div class="form-group"><label for="editContactNo">Contact Number</label><input id="editContactNo" name="contact_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" title="Enter an 11-digit contact number using numbers only" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" required></div>
            <div class="form-group"><label for="editAddress">Address</label><textarea id="editAddress" name="address" rows="3" required></textarea></div>
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" onclick="closeEditMemberModal()">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
<script src="../js/members.js?v=5"></script>
<script src="../js/confirm-modal.js?v=3"></script>
</body>
</html>