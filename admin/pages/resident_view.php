<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$db = database();

$residentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$residentId) {
    redirect('members.php');
}

$residentStmt = $db->prepare('SELECT * FROM residents WHERE id = ? LIMIT 1');
$residentStmt->execute([$residentId]);
$resident = $residentStmt->fetch();
if (!$resident) {
    flash('member_error', 'That resident record could not be found.');
    redirect('members.php');
}

$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $w) {
    $initials .= mb_strtoupper(mb_substr($w, 0, 1));
}

$profileSuccess = flash('personal_success');
$profileError = flash('personal_error');
$spouseSuccess = flash('spouse_success');
$spouseError = flash('spouse_error');
$parentsSuccess = flash('parents_success');
$parentsError = flash('parents_error');
$childrenSuccess = flash('children_success');
$childrenError = flash('children_error');
$referencesSuccess = flash('references_success');
$referencesError = flash('references_error');
$photoSuccess = flash('photo_success');
$photoError = flash('photo_error');
$accountSuccess = flash('member_success');
$accountError = flash('member_error');

$profileStmt = $db->prepare('SELECT last_name, first_name, middle_name, extension_name, sex, civil_status, birthday, occupation, employer, employer_address, photo_path, updated_at FROM resident_profile WHERE resident_id = ? LIMIT 1');
$profileStmt->execute([$residentId]);
$personal = $profileStmt->fetch() ?: null;
$photoPath = $personal['photo_path'] ?? null;

$civilStatusOptions = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'divorced' => 'Divorced'];
$purokOptions = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];

$age = null;
if (!empty($personal['birthday'])) {
    $age = (new DateTime($personal['birthday']))->diff(new DateTime('today'))->y;
}

$derivedHeadName = trim(($personal['first_name'] ?? '') . ' ' . ($personal['middle_name'] ?? '') . ' ' . ($personal['last_name'] ?? '')) . (($personal['extension_name'] ?? '') !== '' ? ' ' . $personal['extension_name'] : '');
$derivedHeadName = preg_replace('/\s+/', ' ', trim($derivedHeadName));
if ($derivedHeadName === '') {
    $derivedHeadName = $resident['head_name'];
}

$spouseStmt = $db->prepare('SELECT spouse_name, occupation, employer, updated_at FROM resident_spouse WHERE resident_id = ? LIMIT 1');
$spouseStmt->execute([$residentId]);
$spouse = $spouseStmt->fetch() ?: null;

$parentsStmt = $db->prepare('SELECT father_name, mother_name, updated_at FROM resident_parents WHERE resident_id = ? LIMIT 1');
$parentsStmt->execute([$residentId]);
$parents = $parentsStmt->fetch() ?: null;

$childrenStmt = $db->prepare('SELECT child_name, sex, age, created_at FROM resident_children WHERE resident_id = ? ORDER BY id ASC');
$childrenStmt->execute([$residentId]);
$children = $childrenStmt->fetchAll();
$childrenUpdated = null;
foreach ($children as $childRow) {
    if ($childRow['created_at'] !== null && ($childrenUpdated === null || $childRow['created_at'] > $childrenUpdated)) {
        $childrenUpdated = $childRow['created_at'];
    }
}

$referencesStmt = $db->prepare('SELECT reference_name, signature_path, created_at FROM resident_references WHERE resident_id = ? ORDER BY id ASC LIMIT 2');
$referencesStmt->execute([$residentId]);
$references = $referencesStmt->fetchAll();
$reference1 = $references[0] ?? null;
$reference2 = $references[1] ?? null;
$referencesUpdated = null;
foreach ($references as $refRow) {
    if ($refRow['created_at'] !== null && ($referencesUpdated === null || $refRow['created_at'] > $referencesUpdated)) {
        $referencesUpdated = $refRow['created_at'];
    }
}

/** Render a small "Last updated" line for a section, or nothing if it has no saved record yet. */
function rv_last_updated(?string $timestamp, string $verb = 'Updated'): string
{
    if (!$timestamp) {
        return '';
    }
    return '<span class="accordion-updated">' . $verb . ' ' . h(date('M j, Y \a\t g:i A', strtotime($timestamp))) . '</span>';
}

$accountOpen = $accountError !== null || $accountSuccess !== null;
$spouseOpen = $spouseError !== null || $spouseSuccess !== null;
$parentsOpen = $parentsError !== null || $parentsSuccess !== null;
$childrenOpen = $childrenError !== null || $childrenSuccess !== null;
$referencesOpen = $referencesError !== null || $referencesSuccess !== null;
$photoOpen = $photoError !== null || $photoSuccess !== null;

// A single "record was just saved" banner covers whichever section the
// staff member last edited, so they always land on one clear place to
// jump to the full, read-only, freshly-saved record — instead of hunting
// for the right accordion's small toast.
$justSaved = $accountSuccess ?? $profileSuccess ?? $spouseSuccess ?? $parentsSuccess ?? $childrenSuccess ?? $referencesSuccess ?? $photoSuccess ?? null;

$activeSection = 'personal';
if ($spouseOpen) {
    $activeSection = 'spouse';
} elseif ($parentsOpen) {
    $activeSection = 'parents';
} elseif ($childrenOpen) {
    $activeSection = 'children';
} elseif ($referencesOpen) {
    $activeSection = 'references';
} elseif ($photoOpen) {
    $activeSection = 'photo';
}
$personalOpen = $activeSection === 'personal';

$childCount = count($children);
$childrenSubtitle = $childCount === 0 ? 'No children added' : implode(', ', array_column($children, 'child_name'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resident Record — <?= h($resident['head_name']) ?></title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css?v=3">
<link rel="stylesheet" href="../../assets/css/profile.css?v=5">
<link rel="stylesheet" href="../css/confirm-modal.css?v=3">
<link rel="stylesheet" href="../css/resident-view.css?v=1">
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

<main class="main" data-active-section="<?= h($activeSection) ?>">
<div class="sticky-head">
    <div class="topbar">
        <div class="rv-header-content">
            <button class="menu-toggle" aria-label="Toggle menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <a class="rv-back" href="members.php">&larr; Back to Member Management</a>
            <div class="page-eyebrow">Resident record</div>
            <h1 class="page-title"><?= h($resident['head_name']) ?></h1>
            <p class="page-sub">View and edit this resident's full personal, family, and reference information.</p>
        </div>
    </div>

    <?php if ($accountError): ?><p class="notice notice-error" role="alert"><?=h($accountError)?></p><?php endif; ?>

    <?php if ($justSaved): ?>
    <div class="notice notice-success rv-saved-banner" role="status">
        <span><?= h($justSaved) ?></span>
    </div>
    <?php endif; ?>
    </div>

    <div class="rv-id-strip card">
        <div class="id-avatar rv-avatar"><?= h($initials) ?></div>
        <div class="rv-id-text">
            <div class="rv-id-name"><?= h($resident['head_name']) ?></div>
            <div class="rv-id-meta">Account No. <?= h($resident['resident_number']) ?> &middot; Household No. <?= h($resident['household_number']) ?> &middot; <span class="status-pill <?= $resident['archived_at'] !== null ? 'is-archived' : ($resident['is_active'] ? 'is-active' : 'is-inactive') ?>"><?= $resident['archived_at'] !== null ? 'Archived' : ($resident['is_active'] ? 'Active' : 'Deactivated') ?></span></div>
        </div>
        <div class="rv-id-actions">
            <form action="../../process/update_member.php" method="post" data-confirm-modal='<?=json_encode(['title'=>'Reset this member\'s password?','description'=>'A new temporary password will be generated for '.$resident['head_name'].'. They will need to use it to log in again.','confirmLabel'=>'Yes, reset password','variant'=>'danger'], JSON_HEX_APOS | JSON_HEX_QUOT)?>'>
                <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="return_to" value="resident_view.php?id=<?=(int)$resident['id']?>">
                <button class="btn btn-outline btn-sm" type="submit">Reset Password</button>
            </form>
            <form action="../../process/update_member.php" method="post" data-confirm-modal='<?=json_encode(['title'=>($resident['is_active']?'Deactivate':'Activate').' this member account?','description'=>$resident['is_active']?$resident['head_name'].' will no longer be able to log in or answer surveys until reactivated.':$resident['head_name'].' will be able to log in and answer surveys again.','confirmLabel'=>$resident['is_active']?'Yes, deactivate':'Yes, activate','variant'=>$resident['is_active']?'danger':'info'], JSON_HEX_APOS | JSON_HEX_QUOT)?>'>
                <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="return_to" value="resident_view.php?id=<?=(int)$resident['id']?>">
                <button class="btn btn-sm <?=$resident['is_active']?'btn-danger':'btn-primary'?>" type="submit"><?=$resident['is_active']?'Deactivate':'Activate'?></button>
            </form>
            <?php if ($resident['archived_at'] === null): ?>
                <form action="../../process/admin_archive_resident.php" method="post" data-confirm-modal='<?=json_encode(['title'=>'Move this resident record to the archive?','description'=>$resident['head_name'].'\'s record will be moved to the Archive and their account deactivated. You can restore it or delete it permanently from the Archive.','confirmLabel'=>'Yes, move to archive','variant'=>'danger'], JSON_HEX_APOS | JSON_HEX_QUOT)?>'>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <button class="btn btn-danger btn-sm" type="submit">Move to Archive</button>
                </form>
            <?php else: ?>
                <form action="../../process/admin_restore_resident.php" method="post" data-confirm-modal='<?=json_encode(['title'=>'Restore this resident record?','description'=>$resident['head_name'].'\'s record will reappear in Member Management. The account stays deactivated until you activate it again.','confirmLabel'=>'Yes, restore','variant'=>'info'], JSON_HEX_APOS | JSON_HEX_QUOT)?>'>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <button class="btn btn-primary btn-sm" type="submit">Restore</button>
                </form>
                <form action="../../process/admin_delete_resident.php" method="post" data-confirm-modal='<?=json_encode(['title'=>'Permanently delete this resident record?','description'=>'This deletes '.$resident['head_name'].'\'s account, household record, and all saved personal, spouse, children, parents, and reference information, plus their photo and signatures. This cannot be undone.','confirmLabel'=>'Yes, delete permanently','variant'=>'danger'], JSON_HEX_APOS | JSON_HEX_QUOT)?>'>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <button class="btn btn-danger btn-sm" type="submit">Delete Permanently</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($resident['archived_at'] !== null): ?>
        <p class="notice notice-error" role="status">This record is archived and hidden from Member Management. <?= h($resident['head_name']) ?> cannot log in until restored and reactivated.</p>
    <?php endif; ?>

    <!-- Household Account + Personal Information (merged) -->
    <details class="card accordion-card" id="account-card" <?= ($personalOpen || $accountOpen) ? 'open' : '' ?>>
        <summary>
            <span class="accordion-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <span class="accordion-heading">
                <span class="accordion-title">Personal Information</span>
                <span class="accordion-subtitle">Login number, address, and personal details</span>
                <?= rv_last_updated($personal['updated_at'] ?? $resident['updated_at'] ?? null) ?>
            </span>
            <span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
        </summary>
        <div class="accordion-body">
            <div class="accordion-body-inner">
                <?php if ($profileSuccess): ?><div class="toast show"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?= h($profileSuccess) ?></div><?php endif; ?>
                <?php if ($profileError): ?><p class="hint" role="alert"><?= h($profileError) ?></p><?php endif; ?>

                <form id="profileForm" method="post" action="../../process/admin_update_personal.php" data-validate>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <input type="hidden" id="headName" name="head_name" value="<?= h($derivedHeadName) ?>">
                    <div class="field">
                        <label for="householdNo">Household No.</label>
                        <input type="text" id="householdNo" name="household_number" value="<?= h($resident['household_number']) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" maxlength="80" value="<?= h($personal['last_name'] ?? '') ?>" required>
                            <p class="field-error">Last name is required.</p>
                        </div>
                        <div class="field">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" maxlength="80" value="<?= h($personal['first_name'] ?? '') ?>" required>
                            <p class="field-error">First name is required.</p>
                        </div>
                    </div>
                    <div class="form-row name-extra-row">
                        <div class="field">
                            <label for="middle_name">Middle Name <span class="hint" style="display:inline">(optional)</span></label>
                            <input type="text" id="middle_name" name="middle_name" maxlength="80" value="<?= h($personal['middle_name'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="extension_name">Extension Name <span class="hint" style="display:inline">(optional)</span></label>
                            <input type="text" id="extension_name" name="extension_name" maxlength="20" placeholder="Jr., Sr., III" value="<?= h($personal['extension_name'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="sex">Sex</label>
                            <select id="sex" name="sex" required>
                                <option value="" disabled <?= empty($personal['sex']) ? 'selected' : '' ?>>Select sex</option>
                                <option value="male" <?= ($personal['sex'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($personal['sex'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                            <p class="field-error">Please select a sex.</p>
                        </div>
                    </div>
                    <div class="form-row name-extra-row">
                        <div class="field">
                            <label for="civil_status">Civil Status</label>
                            <select id="civil_status" name="civil_status" required>
                                <option value="" disabled <?= empty($personal['civil_status']) ? 'selected' : '' ?>>Select civil status</option>
                                <?php foreach ($civilStatusOptions as $value => $label): ?>
                                <option value="<?= h($value) ?>" <?= ($personal['civil_status'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-error">Please select a civil status.</p>
                        </div>
                        <div class="field">
                            <label for="birthday">Birth of Date</label>
                            <input type="date" id="birthday" name="birthday" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= h($personal['birthday'] ?? '') ?>" required>
                            <p class="field-error">Please provide a valid Birth of Date.</p>
                        </div>
                        <div class="field">
                            <label for="age_display">Age</label>
                            <input type="text" id="age_display" value="<?= $age !== null ? (int) $age : '—' ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="contactNo">Contact Number</label>
                            <input type="tel" id="contactNo" name="contact_number" value="<?= h($resident['contact_number']) ?>" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" title="Enter an 11-digit contact number using numbers only" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" required>
                            <p class="field-error">Enter a valid 11-digit contact number.</p>
                        </div>
                        <div class="field">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" required><?= h($resident['address']) ?></textarea>
                            <p class="field-error">Address is required.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="purok">Purok <span class="hint" style="display:inline">(optional)</span></label>
                            <select id="purok" name="purok">
                                <option value="" <?= empty($resident['purok']) ? 'selected' : '' ?>>Select purok</option>
                                <?php foreach ($purokOptions as $purokValue): ?>
                                <option value="<?= h($purokValue) ?>" <?= ($resident['purok'] ?? '') === $purokValue ? 'selected' : '' ?>><?= h($purokValue) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="occupation">Occupation <span class="hint" style="display:inline">(optional)</span></label>
                            <input type="text" id="occupation" name="occupation" maxlength="120" value="<?= h($personal['occupation'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="employer">Employer <span class="hint" style="display:inline">(optional)</span></label>
                            <input type="text" id="employer" name="employer" maxlength="120" value="<?= h($personal['employer'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="employer_address">Employer Address <span class="hint" style="display:inline">(optional)</span></label>
                            <textarea id="employer_address" name="employer_address"><?= h($personal['employer_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Personal Information</button>
                </form>
            </div>
        </div>
    </details>

    <!-- Spouse Information -->
    <details class="card accordion-card" id="spouse-card" <?= $spouseOpen ? 'open' : '' ?>>
        <summary>
            <span class="accordion-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="accordion-heading">
                <span class="accordion-title">Spouse Information</span>
                <span class="accordion-subtitle<?= $spouse ? ' is-complete' : '' ?>"><?= $spouse ? h($spouse['spouse_name']) : 'Not added yet' ?></span>
                <?= rv_last_updated($spouse['updated_at'] ?? null) ?>
            </span>
            <span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
        </summary>
        <div class="accordion-body">
            <div class="accordion-body-inner">
                <?php if ($spouseSuccess): ?><div class="toast show"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?= h($spouseSuccess) ?></div><?php endif; ?>
                <?php if ($spouseError): ?><p class="hint" role="alert"><?= h($spouseError) ?></p><?php endif; ?>

                <form method="post" action="../../process/admin_update_spouse.php" data-validate>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <div class="field">
                        <label for="spouse_name">Spouse Name <span class="hint" style="display:inline">(leave all spouse fields blank if not applicable)</span></label>
                        <input type="text" id="spouse_name" name="spouse_name" maxlength="120" value="<?= h($spouse['spouse_name'] ?? '') ?>">
                        <p class="field-error">Spouse name is required if any spouse detail is filled in.</p>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="spouse_occupation">Occupation <span class="hint" style="display:inline">(optional)</span></label>
                            <input type="text" id="spouse_occupation" name="spouse_occupation" maxlength="120" value="<?= h($spouse['occupation'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="spouse_employer">Employer <span class="hint" style="display:inline">(optional)</span></label>
                            <input type="text" id="spouse_employer" name="spouse_employer" maxlength="120" value="<?= h($spouse['employer'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Spouse Information</button>
                </form>
            </div>
        </div>
    </details>

    <!-- Parents -->
    <details class="card accordion-card" id="parents-card" <?= $parentsOpen ? 'open' : '' ?>>
        <summary>
            <span class="accordion-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 9v10a1 1 0 0 0 1 1h4v-5a2 2 0 0 1 2-2 2 2 0 0 1 2 2v5h4a1 1 0 0 0 1-1V9"/><path d="M9.5 12.5h5"/></svg></span>
            <span class="accordion-heading">
                <span class="accordion-title">Parents Information</span>
                <span class="accordion-subtitle<?= $parents ? ' is-complete' : '' ?>"><?= $parents ? h($parents['father_name']) . ' & ' . h($parents['mother_name']) : 'Not added yet' ?></span>
                <?= rv_last_updated($parents['updated_at'] ?? null) ?>
            </span>
            <span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
        </summary>
        <div class="accordion-body">
            <div class="accordion-body-inner">
                <?php if ($parentsSuccess): ?><div class="toast show"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?= h($parentsSuccess) ?></div><?php endif; ?>
                <?php if ($parentsError): ?><p class="hint" role="alert"><?= h($parentsError) ?></p><?php endif; ?>

                <form method="post" action="../../process/admin_update_parents.php" data-validate>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <div class="form-row">
                        <div class="field">
                            <label for="father_name">Father's Name</label>
                            <input type="text" id="father_name" name="father_name" maxlength="120" value="<?= h($parents['father_name'] ?? '') ?>" required>
                            <p class="field-error">Father's name is required.</p>
                        </div>
                        <div class="field">
                            <label for="mother_name">Mother's Name</label>
                            <input type="text" id="mother_name" name="mother_name" maxlength="120" value="<?= h($parents['mother_name'] ?? '') ?>" required>
                            <p class="field-error">Mother's name is required.</p>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Parents Information</button>
                </form>
            </div>
        </div>
    </details>

    <!-- Children -->
    <details class="card accordion-card" id="children-card" <?= $childrenOpen ? 'open' : '' ?>>
        <summary>
            <span class="accordion-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 21c0-3.6 3-6 7-6s7 2.4 7 6"/></svg></span>
            <span class="accordion-heading">
                <span class="accordion-title">Children Information</span>
                <span class="accordion-subtitle<?= $childCount ? ' is-complete' : '' ?>"><?= h($childrenSubtitle) ?></span>
                <?= rv_last_updated($childrenUpdated, 'Saved') ?>
            </span>
            <span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
        </summary>
        <div class="accordion-body">
            <div class="accordion-body-inner">
                <?php if ($childrenSuccess): ?><div class="toast show"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?= h($childrenSuccess) ?></div><?php endif; ?>
                <?php if ($childrenError): ?><p class="hint" role="alert"><?= h($childrenError) ?></p><?php endif; ?>

                <form method="post" action="../../process/admin_update_children.php" data-validate>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <div id="childrenRows">
                        <?php foreach ($children as $child): ?>
                        <div class="repeat-row">
                            <div class="repeat-row-head">
                                <span class="repeat-row-label">Child</span>
                                <button type="button" class="repeat-row-remove" aria-label="Remove this child" data-remove-row><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                            </div>
                            <div class="form-row child-row">
                                <div class="field">
                                    <label>Child's Name</label>
                                    <input type="text" name="child_name[]" maxlength="120" value="<?= h($child['child_name']) ?>" required>
                                    <p class="field-error">Child's name is required.</p>
                                </div>
                                <div class="field">
                                    <label>Sex</label>
                                    <select name="child_sex[]" required>
                                        <option value="" disabled <?= empty($child['sex']) ? 'selected' : '' ?>>Select sex</option>
                                        <option value="male" <?= ($child['sex'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($child['sex'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                    <p class="field-error">Select a sex.</p>
                                </div>
                                <div class="field">
                                    <label>Age</label>
                                    <input type="number" name="child_age[]" min="0" max="120" value="<?= h((string) $child['age']) ?>" required>
                                    <p class="field-error">Enter a valid age.</p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="repeat-empty" id="childrenEmpty" <?= $children ? 'hidden' : '' ?>>No children added yet.</p>
                    <button type="button" class="add-row-btn" id="addChildRow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Add a Child</button>
                    <button type="submit" class="btn btn-primary">Save Children Information</button>
                </form>
            </div>
        </div>
    </details>

    <!-- Character References -->
    <details class="card accordion-card" id="references-card" <?= $referencesOpen ? 'open' : '' ?>>
        <summary>
            <span class="accordion-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z"/><path d="M9 15s.8 1 3 1 3-1 3-1"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></span>
            <span class="accordion-heading">
                <span class="accordion-title">Character References</span>
                <span class="accordion-subtitle<?= ($reference1 && $reference2) ? ' is-complete' : '' ?>"><?= ($reference1 && $reference2) ? 'Added' : 'Not added yet' ?></span>
                <?= rv_last_updated($referencesUpdated, 'Saved') ?>
            </span>
            <span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
        </summary>
        <div class="accordion-body">
            <div class="accordion-body-inner">
                <?php if ($referencesSuccess): ?><div class="toast show"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?= h($referencesSuccess) ?></div><?php endif; ?>
                <?php if ($referencesError): ?><p class="hint" role="alert"><?= h($referencesError) ?></p><?php endif; ?>

                <form method="post" action="../../process/admin_update_references.php" enctype="multipart/form-data" data-validate>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <?php foreach ([1 => $reference1, 2 => $reference2] as $i => $ref): ?>
                    <div class="repeat-row reference-row">
                        <div class="repeat-row-head">
                            <span class="repeat-row-label">Reference <?= $i ?></span>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label for="reference_name_<?= $i ?>">Full Name</label>
                                <input type="text" id="reference_name_<?= $i ?>" name="reference_name_<?= $i ?>" maxlength="120" value="<?= h($ref['reference_name'] ?? '') ?>" required>
                                <p class="field-error">This reference's name is required.</p>
                            </div>
                            <div class="field">
                                <label for="signature_<?= $i ?>">Signature <span class="hint" style="display:inline">(optional)</span></label>
                                <div class="signature-field">
                                    <div class="signature-preview <?= empty($ref['signature_path']) ? 'is-empty' : '' ?>" id="signaturePreview<?= $i ?>">
                                        <?php if (!empty($ref['signature_path'])): ?>
                                            <img src="../../assets/<?= h($ref['signature_path']) ?>" alt="Signature <?= $i ?>">
                                        <?php else: ?>
                                            No file
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-input-wrap">
                                        <input type="file" id="signature_<?= $i ?>" name="signature_<?= $i ?>" accept="image/png,image/jpeg,image/webp" data-signature-input="<?= $i ?>" hidden>
                                        <div class="signature-actions">
                                            <button type="button" class="btn btn-outline btn-sm" data-choose-signature="<?= $i ?>">Choose File</button>
                                            <?php if (!empty($ref['signature_path'])): ?>
                                            <label class="signature-remove-check">
                                                <input type="checkbox" name="remove_signature_<?= $i ?>" value="1">
                                                Remove current signature
                                            </label>
                                            <?php endif; ?>
                                        </div>
                                        <p class="signature-file-name" id="signatureFileName<?= $i ?>"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary">Save Character References</button>
                </form>
            </div>
        </div>
    </details>

    <!-- Profile Photo -->
    <details class="card accordion-card" id="photo-card" <?= $photoOpen ? 'open' : '' ?>>
        <summary>
            <span class="accordion-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg></span>
            <span class="accordion-heading">
                <span class="accordion-title">Profile Photo</span>
                <span class="accordion-subtitle<?= $photoPath ? ' is-complete' : '' ?>"><?= $photoPath ? 'Photo uploaded' : 'No photo uploaded yet' ?></span>
            </span>
            <span class="accordion-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
        </summary>
        <div class="accordion-body">
            <div class="accordion-body-inner">
                <?php if ($photoSuccess): ?><div class="toast show"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><?= h($photoSuccess) ?></div><?php endif; ?>
                <?php if ($photoError): ?><p class="hint" role="alert"><?= h($photoError) ?></p><?php endif; ?>

                <form method="post" action="../../process/admin_update_photo.php" enctype="multipart/form-data" id="photoForm">
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <div class="photo-upload-row">
                        <div class="photo-preview" id="photoPreview">
                            <?php if ($photoPath): ?>
                                <img src="../../assets/<?= h($photoPath) ?>" alt="Profile photo">
                            <?php else: ?>
                                <span><?= h($initials) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="hint">JPG, PNG, or WEBP. Max size 3MB.</p>
                        <input type="file" id="photoInput" name="photo" accept="image/png,image/jpeg,image/webp" hidden>
                        <p class="photo-file-name" id="photoFileName"></p>
                        <button type="button" class="btn btn-outline" id="choosePhotoBtn">Choose Photo</button>
                    </div>
                    <p class="hint" role="alert" id="photoClientError" style="display:none;margin-top:14px;"></p>
                    <button type="submit" class="btn btn-primary" id="savePhotoBtn" style="margin-top:16px;">Save Profile Photo</button>
                </form>
                <?php if ($photoPath): ?>
                <form method="post" action="../../process/admin_remove_photo.php" class="remove-photo-form" data-confirm-modal='{"title":"Remove profile photo?","description":"This will permanently delete this resident\'s current profile photo. This cannot be undone.","confirmLabel":"Yes, remove photo","variant":"danger"}'>
                    <input type="hidden" name="resident_id" value="<?=(int)$resident['id']?>">
                    <button type="submit" class="btn-remove-photo" id="removePhotoBtn">Remove current photo</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </details>

</main>
</div>

<script src="../../assets/js/dashboard.js?v=11"></script>
<script src="../../assets/js/profile.js?v=4"></script>
<script src="../js/logout.js"></script>
<script src="../js/confirm-modal.js?v=3"></script>
<script>
(function () {
    var birthday = document.getElementById('birthday');
    var age = document.getElementById('age_display');
    if (!birthday || !age) return;
    birthday.addEventListener('change', function () {
        if (!birthday.value) { age.value = '—'; return; }
        var birthDate = new Date(birthday.value + 'T00:00:00');
        var today = new Date();
        var years = today.getFullYear() - birthDate.getFullYear();
        if (today.getMonth() < birthDate.getMonth() || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) years--;
        age.value = years >= 0 ? years : '—';
    });
}());

(function () {
    // Head of Household is just this resident's own name — derive it instead
    // of asking the admin to keep it in sync by hand. If the name fields are
    // ever all blank (a household-only record with no profile yet), leave
    // the field's existing value alone rather than wiping it out.
    var headName = document.getElementById('headName');
    var lastName = document.getElementById('last_name');
    var firstName = document.getElementById('first_name');
    var middleName = document.getElementById('middle_name');
    var extensionName = document.getElementById('extension_name');
    if (!headName) return;
    function updateHeadName() {
        var parts = [firstName?.value.trim(), middleName?.value.trim(), lastName?.value.trim()].filter(Boolean);
        var derived = parts.join(' ');
        if (extensionName?.value.trim()) derived += (derived ? ' ' : '') + extensionName.value.trim();
        if (derived) headName.value = derived;
    }
    [lastName, firstName, middleName, extensionName].forEach(function (el) {
        el?.addEventListener('input', updateHeadName);
    });
}());
</script>
</body>
</html>