<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
if ($admin['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = database();
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$statusFilter = in_array($statusFilter, ['all', 'active', 'inactive'], true) ? $statusFilter : 'all';

$where = ['archived_at IS NULL', "role = 'staff'"];
$params = [];
if ($search !== '') {
    $where[] = '(username LIKE ? OR full_name LIKE ? OR role LIKE ?)';
    for ($i = 0; $i < 3; $i++) $params[] = '%' . $search . '%';
}
if ($statusFilter === 'active') {
    $where[] = 'is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'is_active = 0';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStatement = $db->prepare("SELECT COUNT(*) FROM staff_admin $whereSql");
$countStatement->execute($params);
$userCount = (int) $countStatement->fetchColumn();
$pageCount = max(1, (int) ceil($userCount / $perPage));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pageCount);
$offset = ($page - 1) * $perPage;

$userStatement = $db->prepare(
    "SELECT id, username, full_name, role, is_active, created_at
     FROM staff_admin
     $whereSql
     ORDER BY id DESC
     LIMIT $perPage OFFSET $offset"
);
$userStatement->execute($params);
$users = $userStatement->fetchAll();
$success = flash('user_success');
$error = flash('user_error');
$passwordError = flash('password_error');
$passwordOldNew = $_SESSION['admin_old_new_password'] ?? '';
$passwordOldConfirm = $_SESSION['admin_old_confirm_password'] ?? '';
$openPasswordModal = !empty($_SESSION['admin_open_password_modal']);
$openPasswordModalStaffId = (int) ($_SESSION['admin_open_password_modal_staff_id'] ?? $admin['id']);
unset($_SESSION['admin_old_new_password'], $_SESSION['admin_old_confirm_password'], $_SESSION['admin_open_password_modal'], $_SESSION['admin_open_password_modal_staff_id']);

$openPasswordModalUsername = $admin['username'];
$openPasswordModalIsOwn = $openPasswordModalStaffId === (int) $admin['id'];
if ($openPasswordModal && !$openPasswordModalIsOwn) {
    $targetStmt = $db->prepare('SELECT username FROM staff_admin WHERE id = ? LIMIT 1');
    $targetStmt->execute([$openPasswordModalStaffId]);
    $openPasswordModalUsername = (string) ($targetStmt->fetchColumn() ?: $admin['username']);
}

function render_user_results(array $users, int $userCount, int $perPage, string $search, string $statusFilter, int $page, int $pageCount, array $admin): string
{
    ob_start();
    ?>
        <div class="card-title-row">
            <div>
                <div class="card-title">Staff Accounts</div>
                <div class="card-desc"><?=$userCount?> staff account<?=$userCount===1?'':'s'?> in the system<?= $userCount > $perPage ? ' · Page ' . $page . ' of ' . $pageCount : '' ?><?= ($search !== '' || $statusFilter !== 'all') ? ' · Filtered' : '' ?>.</div>
            </div>
        </div>
        <table class="responsive-table">
            <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6" data-label=""><?= ($search !== '' || $statusFilter !== 'all') ? 'No staff accounts match your search or filter.' : 'No staff accounts have been created yet.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td data-label="Username"><?=h($u['username'])?></td>
                    <td data-label="Name"><?=h($u['full_name'])?></td>
                    <td data-label="Role"><?=h(ucfirst($u['role']))?></td>
                    <td data-label="Status"><span class="badge <?=$u['is_active']?'badge-success':'badge-warning'?>"><?=$u['is_active']?'Active':'Inactive'?></span></td>
                    <td data-label="Created"><?=h(date('M j, Y', strtotime($u['created_at'])))?></td>
                    <td data-label="Actions">
                        <div class="row-actions user-row-actions">
                            <button class="btn btn-outline btn-sm user-icon-action" type="button" aria-label="Edit staff account" data-tooltip="Edit"
                                onclick='openEditUserModal(<?=json_encode([
                                    "id" => (int) $u["id"],
                                    "full_name" => $u["full_name"],
                                    "role" => $u["role"],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT)?>)'><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h3.5L18.4 9.1l-3.5-3.5L4 16.5Z"/><path d="m13.9 6.1 3.5 3.5"/></svg></button>
                            <button class="btn btn-outline btn-sm user-icon-action" type="button" aria-label="Change <?= h($u['username']) ?> password" data-tooltip="Change password"
                                onclick='openChangePasswordModal(<?=json_encode([
                                    "id" => (int) $u["id"],
                                    "username" => $u["username"],
                                    "isOwn" => (int) $u["id"] === (int) $admin["id"],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT)?>)'><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M10.85 12.15 19 4"/><path d="M18 5l2 2"/><path d="M15 8l2 2"/></svg></button>
                            <?php if ((int) $u['id'] !== (int) $admin['id']): ?>
                                <?php
                                    $toggleConfirm = json_encode([
                                        'title' => ($u['is_active'] ? 'Deactivate' : 'Activate') . ' this staff account?',
                                        'description' => $u['is_active']
                                            ? $u['full_name'] . ' will no longer be able to log in to the admin portal until reactivated.'
                                            : $u['full_name'] . ' will be able to log in to the admin portal again.',
                                        'confirmLabel' => $u['is_active'] ? 'Yes, deactivate' : 'Yes, activate',
                                        'variant' => $u['is_active'] ? 'danger' : 'info',
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                <form action="../../process/update_staff.php" method="post" data-confirm-modal='<?=$toggleConfirm?>'>
                                    <input type="hidden" name="staff_id" value="<?=(int) $u['id']?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn btn-sm <?=$u['is_active']?'btn-danger':'btn-primary'?> user-icon-action" type="submit" aria-label="<?=$u['is_active']?'Deactivate':'Activate'?> staff account" data-tooltip="<?=$u['is_active']?'Deactivate':'Activate'?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v10"/><path d="M6.2 5.2a8 8 0 1 0 11.6 0"/></svg></button>
                                </form>
                                <?php
                                    $archiveConfirm = json_encode([
                                        'title' => 'Archive this staff account?',
                                        'description' => $u['full_name'] . ' will be moved to the Archive and deactivated. You can restore it from there at any time.',
                                        'confirmLabel' => 'Yes, archive',
                                        'variant' => 'danger',
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                <form action="../../process/admin_archive_staff.php" method="post" data-confirm-modal='<?=$archiveConfirm?>'>
                                    <input type="hidden" name="staff_id" value="<?=(int) $u['id']?>">
                                    <button class="btn btn-danger btn-sm user-icon-action" type="submit" aria-label="Archive staff account" data-tooltip="Archive"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v4H3z"/><path d="M5 8v11h14V8"/><path d="M10 12h4"/></svg></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($userCount > 0): ?>
        <nav class="management-pagination" aria-label="Staff account pages">
            <?php if ($page > 1): ?><a class="btn btn-outline" data-page="<?= $page - 1 ?>" href="users.php?page=<?= $page - 1 ?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pageCount ?></span>
            <?php if ($page < $pageCount): ?><a class="btn btn-outline" data-page="<?= $page + 1 ?>" href="users.php?page=<?= $page + 1 ?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}

$resultsHtml = render_user_results($users, $userCount, $perPage, $search, $statusFilter, $page, $pageCount, $admin);

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
<title>User Management</title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css?v=3">
<link rel="stylesheet" href="../css/users.css?v=4">
<link rel="stylesheet" href="../css/confirm-modal.css?v=3">
</head>
<body>
<div class="portal-shell">
<div class="sidebar-backdrop"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
    <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow">Administrator Account</div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
    <nav class="nav-group"><span class="nav-label">Management</span>
        <a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
        <a class="nav-link" href="surveys.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
        <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
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
        <a class="nav-link" href="users.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a>
        
    </nav>
    <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
</aside>
<main class="main">
    <div class="sticky-head">
    <div class="topbar">
        <div>
            <button class="menu-toggle" aria-label="Toggle menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <div class="page-eyebrow">Staff access</div>
            <h1 class="page-title">User Management</h1>
            <p class="page-sub">Staff accounts with access to the portal.</p>
        </div>
        <button class="btn btn-primary" type="button" onclick="openAddUserModal()">+ Add Staff</button>
    </div>
    <?php if ($success): ?><p class="notice notice-success" role="status"><?=h($success)?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?=h($error)?></p><?php endif; ?>


    <form class="user-filters card" method="get" role="search" id="userFilterForm">
        <div class="user-filters-row">
            <label class="user-filter-field">
                <span class="user-filter-label">Search staff</span>
                <input type="search" name="q" id="userSearchInput" value="<?= h($search) ?>" placeholder="Search by username, name, or role" autocomplete="off">
            </label>
            <label class="user-filter-field user-filter-field-status">
                <span class="user-filter-label">Status</span>
                <select name="status" id="userStatusSelect">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </label>
        </div>
    </form>
    </div>

    <div class="card">
        <div id="userResults"><?= $resultsHtml ?></div>
    </div>
</main>
</div>

<!-- Add Staff Modal -->
<div class="modal-overlay" id="addUserOverlay">
    <div class="modal-box">
        <div class="modal-head">
            <div><h2>Add Staff Account</h2><p>Create a new portal login for a staff member.</p></div>
            <button class="modal-close" type="button" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form action="../../process/create_staff.php" method="post">
            <div class="form-group"><label for="userFullName">Full Name</label><input id="userFullName" name="full_name" type="text" required></div>
            <div class="form-group"><label for="username">Username</label><input id="username" name="username" type="text" required></div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required minlength="8">
                    <button type="button" class="toggle-password" data-target="password" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 4.22-5.11M9.9 4.24A9.75 9.75 0 0 1 12 4c7 0 11 7 11 7a20.6 20.6 0 0 1-2.06 2.94M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <p class="form-hint">At least 8 characters, with letters and numbers.</p>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm Password</label>
                <div class="password-field">
                    <input id="confirmPassword" name="confirm_password" type="password" required minlength="8">
                    <button type="button" class="toggle-password" data-target="confirmPassword" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 4.22-5.11M9.9 4.24A9.75 9.75 0 0 1 12 4c7 0 11 7 11 7a20.6 20.6 0 0 1-2.06 2.94M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>
            <input type="hidden" name="role" value="staff">
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" onclick="closeAddUserModal()">Cancel</button>
                <button class="btn btn-primary" type="submit">Add Staff</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal-overlay" id="editUserOverlay">
    <div class="modal-box">
        <div class="modal-head">
            <div><h2>Edit Staff Account</h2><p>Update this staff member's name or role.</p></div>
            <button class="modal-close" type="button" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form action="../../process/update_staff.php" method="post">
            <input type="hidden" name="staff_id" id="editStaffId">
            <input type="hidden" name="action" value="save">
            <div class="form-group"><label for="editUserFullName">Full Name</label><input id="editUserFullName" name="full_name" type="text" required></div>
            <input type="hidden" id="editRole" name="role" value="staff">
            <div class="modal-actions">
                <button class="btn btn-outline" type="button" onclick="closeEditUserModal()">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="changePasswordOverlay">
    <div class="modal-box cp-modal">
        <div class="modal-head">
            <div><h2>Change Password</h2><p id="changePasswordDesc">Update this staff member's password.</p></div>
            <button class="modal-close" type="button" onclick="closeChangePasswordModal()">&times;</button>
        </div>
        <?php if ($passwordError): ?>
        <div class="cp-error-alert" id="cpPasswordError" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><?= h($passwordError) ?></span>
        </div>
        <?php endif; ?>
        <form action="../../process/admin_change_password.php" method="post">
            <input type="hidden" name="staff_id" id="cpStaffId">

            <div class="form-group cp-field" id="cpCurrentPasswordGroup" hidden>
                <label for="cpCurrentPassword">Current Password</label>
                <div class="password-field">
                    <input id="cpCurrentPassword" name="current_password" type="password" placeholder="Enter current password">
                    <button type="button" class="toggle-password" data-target="cpCurrentPassword" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 4.22-5.11M9.9 4.24A9.75 9.75 0 0 1 12 4c7 0 11 7 11 7a20.6 20.6 0 0 1-2.06 2.94M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group cp-field">
                <label for="cpNewPassword">New Password</label>
                <div class="password-field">
                    <input id="cpNewPassword" name="new_password" type="password" value="<?= h($passwordOldNew) ?>" placeholder="Enter new password" required minlength="8">
                    <button type="button" class="toggle-password" data-target="cpNewPassword" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 4.22-5.11M9.9 4.24A9.75 9.75 0 0 1 12 4c7 0 11 7 11 7a20.6 20.6 0 0 1-2.06 2.94M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="cp-strength-bar" id="cpStrengthBar"><span></span><span></span><span></span><span></span></div>
                <ul class="cp-requirements" id="cpRequirements">
                    <li data-rule="length"><span class="cp-req-dot"></span>At least 8 characters</li>
                    <li data-rule="upper"><span class="cp-req-dot"></span>At least 1 uppercase letter</li>
                    <li data-rule="number"><span class="cp-req-dot"></span>At least 1 number</li>
                    <li data-rule="special"><span class="cp-req-dot"></span>At least 1 special character</li>
                </ul>
            </div>

            <div class="form-group cp-field">
                <label for="cpConfirmPassword">Confirm New Password</label>
                <div class="password-field">
                    <input id="cpConfirmPassword" name="confirm_password" type="password" value="<?= h($passwordOldConfirm) ?>" placeholder="Re-enter new password" required minlength="8">
                    <button type="button" class="toggle-password" data-target="cpConfirmPassword" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 4.22-5.11M9.9 4.24A9.75 9.75 0 0 1 12 4c7 0 11 7 11 7a20.6 20.6 0 0 1-2.06 2.94M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="cp-match-hint" id="cpMatchHint" aria-live="polite"><span id="cpMatchHintText"></span></div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-outline" type="button" onclick="closeChangePasswordModal()">Cancel</button>
                <button class="btn btn-dark" id="cpSubmitBtn" type="submit" disabled>Update Password</button>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
<script src="../js/users.js?v=4"></script>
<script src="../js/confirm-modal.js?v=3"></script>
<script>
<?php if ($openPasswordModal): ?>
document.addEventListener('DOMContentLoaded', function () {
    openChangePasswordModal(<?= json_encode([
        'id' => $openPasswordModalStaffId,
        'username' => $openPasswordModalUsername,
        'isOwn' => $openPasswordModalIsOwn,
    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, true);
});
<?php endif; ?>
</script>
</body>
</html>