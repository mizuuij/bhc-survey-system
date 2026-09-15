<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
$currentAdmin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/archive.php');
}

$staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
if (!$staffId) {
    redirect('../admin/pages/archive.php');
}

$db = database();
$statement = $db->prepare('SELECT full_name, role, archived_at FROM staff_admin WHERE id = ?');
$statement->execute([$staffId]);
$staff = $statement->fetch();

if (!$staff) {
    flash('archive_error', 'That staff account could not be found.');
    redirect('../admin/pages/archive.php');
}

if (($currentAdmin['role'] ?? 'unknown') === 'staff' && $staff['role'] === 'admin') {
    flash('archive_error', 'Staff accounts do not have permission to modify Admin accounts.');
    redirect('../admin/pages/archive.php');
}

if ($staff['archived_at'] === null) {
    // Not archived — nothing to restore.
    redirect('../admin/pages/archive.php');
}

// Restored accounts stay deactivated until the admin explicitly re-activates
// them from User Management, so nobody regains login access by accident.
$update = $db->prepare('UPDATE staff_admin SET archived_at = NULL WHERE id = ?');
$update->execute([$staffId]);
log_activity($db, 'Updated', 'Staff account', $staff['full_name']);

flash('archive_success', $staff['full_name'] . '\'s account was restored to User Management.');
redirect('../admin/pages/archive.php');