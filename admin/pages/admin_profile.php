<?php
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$success = flash('profile_success');
$error = flash('profile_error');
$passwordSuccess = flash('user_success');
$passwordError = flash('password_error');
$passwordOldNew = $_SESSION['admin_old_new_password'] ?? '';
$passwordOldConfirm = $_SESSION['admin_old_confirm_password'] ?? '';
$adminAge = null;
if (!empty($admin['birthday'])) {
    $adminAge = (new DateTime($admin['birthday']))->diff(new DateTime('today'))->y;
}
$accountLabel = $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Personal Information</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=14">
<link rel="stylesheet" href="../../assets/css/admin-management.css">
<link rel="stylesheet" href="../../assets/css/changepassword.css?v=8">
<link rel="stylesheet" href="../css/logout.css">
</head>
<body>
<div class="portal-shell">
    <div class="sidebar-backdrop"></div>
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
        <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= h($accountLabel) ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
        <nav class="nav-group"><span class="nav-label">Management</span>
            <a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
            <a class="nav-link" href="surveys.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
            <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a>
            <a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
            <a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a>
            <div class="nav-group-item"><button type="button" class="nav-link nav-parent" aria-expanded="false"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span><span>Reports</span><svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></button><div class="nav-submenu"><a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a><a class="nav-link nav-sublink" href="member_reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a></div></div>
            <?php if ($admin['role'] === 'admin'): ?><a class="nav-link" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a><?php endif; ?>
        </nav>
        <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
    </aside>
    <main class="main">
        <div class="sticky-head"><div class="topbar">
            <div>
                <button class="menu-toggle" aria-label="Toggle menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="page-eyebrow">Account settings</div>
                <h1 class="page-title">My Personal Information</h1>
                <p class="page-sub">Update the information shown on your <?= h($accountLabel) ?>.</p>
            </div>
        </div></div>

        <?php if ($success): ?><div class="notice notice-success" role="status"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
        <div class="card"><div class="card-title-row"><div><div class="card-title">Personal Information</div><div class="card-desc">These details belong to your administrator or staff account.</div></div></div>
            <form method="post" action="../../process/update_admin_profile.php" class="profile-form">
                <div class="form-row">
                    <div class="form-group"><label for="full_name">Full Name</label><input id="full_name" name="full_name" type="text" value="<?= h($admin['full_name']) ?>" maxlength="120" required></div>
                    <div class="form-group"><label for="username">Username</label><input id="username" type="text" value="<?= h($admin['username']) ?>" readonly><div class="form-hint">Username changes are managed by an administrator.</div></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="contact_number">Contact Number</label><input id="contact_number" name="contact_number" type="tel" value="<?= h($admin['contact_number'] ?? '') ?>" maxlength="30"></div>
                    <div class="form-group"><label for="birthday">Birth of Date <span id="age-display"><?= $adminAge !== null ? '(Age ' . (int) $adminAge . ')' : '' ?></span></label><input id="birthday" name="birthday" type="date" value="<?= h($admin['birthday'] ?? '') ?>" min="1900-01-01" max="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-group"><label for="address">Address</label><textarea id="address" name="address" rows="4" maxlength="1000"><?= h($admin['address'] ?? '') ?></textarea></div>
                <div class="modal-actions"><button class="btn btn-primary" type="submit">Save Personal Information</button></div>
            </form>
        </div>
        <div class="card profile-password-card">
            <div class="cp-head"><h2>Change Password</h2><p>Update your own <?= h(strtolower($accountLabel)) ?> password.</p></div>
            <?php if ($passwordSuccess): ?><div class="toast show" role="status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5"/></svg><?= h($passwordSuccess) ?></div><?php endif; ?>
            <?php if ($passwordError): ?><div class="toast toast-error show" role="alert"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg><?= h($passwordError) ?></div><?php endif; ?>
            <form id="passwordForm" method="post" action="../../process/admin_change_password.php">
                <input type="hidden" name="staff_id" value="<?= (int) $admin['id'] ?>"><input type="hidden" name="return_to" value="admin_profile.php">
                <div class="field cp-field"><label for="current_password">Current Password</label><div class="password-field"><input type="password" id="current_password" name="current_password" placeholder="Enter current password" required><button type="button" class="toggle-password" data-target="current_password" aria-label="Show password" aria-pressed="false"><svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display:none"><path d="M1 1l22 22"/><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7"/></svg></button></div></div>
                <div class="field cp-field"><label for="new_password">New Password</label><div class="password-field"><input type="password" id="new_password" name="new_password" value="<?= h($passwordOldNew) ?>" placeholder="Enter new password" required><button type="button" class="toggle-password" data-target="new_password" aria-label="Show password" aria-pressed="false"><svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display:none"><path d="M1 1l22 22"/><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7"/></svg></button></div><div class="cp-strength-bar" id="cpStrengthBar"><span></span><span></span><span></span><span></span></div><ul class="cp-requirements" id="cpRequirements"><li data-rule="length"><span class="cp-req-dot"></span>At least 8 characters</li><li data-rule="upper"><span class="cp-req-dot"></span>At least 1 uppercase letter</li><li data-rule="number"><span class="cp-req-dot"></span>At least 1 number</li><li data-rule="special"><span class="cp-req-dot"></span>At least 1 special character</li></ul></div>
                <div class="field cp-field"><label for="confirm_password">Confirm New Password</label><div class="password-field"><input type="password" id="confirm_password" name="confirm_password" value="<?= h($passwordOldConfirm) ?>" placeholder="Re-enter new password" required><button type="button" class="toggle-password" data-target="confirm_password" aria-label="Show password" aria-pressed="false"><svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display:none"><path d="M1 1l22 22"/><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7"/></svg></button></div><div class="cp-match-hint" id="cpMatchHint"><span id="cpMatchHintText"></span></div></div>
                <div class="modal-actions"><a href="dashboard.php" class="btn btn-outline">Cancel</a><button type="submit" class="btn btn-dark" id="cpSubmitBtn">Update Password</button></div>
            </form>
        </div>
    </main>
</div>
<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script><script src="../../assets/js/changepassword.js?v=3"></script>
<script>
(function () {
    var birthday = document.getElementById('birthday');
    var ageDisplay = document.getElementById('age-display');
    if (!birthday || !ageDisplay) return;
    birthday.addEventListener('change', function () {
        if (!birthday.value) { ageDisplay.textContent = ''; return; }
        var birthDate = new Date(birthday.value + 'T00:00:00');
        var today = new Date();
        var age = today.getFullYear() - birthDate.getFullYear();
        var birthdayPassed = today.getMonth() > birthDate.getMonth() || (today.getMonth() === birthDate.getMonth() && today.getDate() >= birthDate.getDate());
        ageDisplay.textContent = '(Age ' + (birthdayPassed ? age : age - 1) + ')';
    });
}());
</script>
</body>
</html>
