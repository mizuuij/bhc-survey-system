<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
$currentAdmin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/users.php');
}

$staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
if (!$staffId) {
    redirect('../admin/pages/users.php');
}

$db = database();
$statement = $db->prepare('SELECT full_name, role, is_active, archived_at FROM staff_admin WHERE id = ?');
$statement->execute([$staffId]);
$staff = $statement->fetch();

if (!$staff) {
    flash('user_error', 'That staff account could not be found.');
    redirect('../admin/pages/users.php');
}

if ($staffId === (int) $currentAdmin['id']) {
    flash('user_error', 'You cannot archive your own account.');
    redirect('../admin/pages/users.php');
}

if (($currentAdmin['role'] ?? 'unknown') === 'staff' && $staff['role'] === 'admin') {
    flash('user_error', 'Staff accounts do not have permission to modify Admin accounts.');
    redirect('../admin/pages/users.php');
}

if ($staff['archived_at'] !== null) {
    // Already archived — nothing to do.
    redirect('../admin/pages/users.php');
}

if ($staff['role'] === 'admin' && (int) $staff['is_active'] === 1) {
    $activeAdmins = (int) $db->query("SELECT COUNT(*) FROM staff_admin WHERE role = 'admin' AND is_active = 1 AND archived_at IS NULL")->fetchColumn();
    if ($activeAdmins <= 1) {
        flash('user_error', 'At least one active administrator account must remain.');
        redirect('../admin/pages/users.php');
    }
}

// Archiving also deactivates the account so it can no longer be used to log
// in to the admin portal while it sits in the archive.
$update = $db->prepare('UPDATE staff_admin SET archived_at = NOW(), is_active = 0 WHERE id = ?');
$update->execute([$staffId]);
log_activity($db, 'Archived', 'Staff account', $staff['full_name']);

flash('user_success', $staff['full_name'] . '\'s account was moved to the archive. You can restore it or delete it permanently from there.');
redirect('../admin/pages/users.php');