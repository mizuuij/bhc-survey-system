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
    // Safety net: permanent deletion is only allowed from the Archive, so a
    // record must be archived first before it can be deleted for good.
    flash('user_error', 'Archive this account first before deleting it permanently.');
    redirect('../admin/pages/users.php');
}

if ($staffId === (int) $currentAdmin['id']) {
    flash('archive_error', 'You cannot delete your own account.');
    redirect('../admin/pages/archive.php');
}

$db->prepare('DELETE FROM staff_admin WHERE id = ?')->execute([$staffId]);

log_activity($db, 'Deleted', 'Staff account', $staff['full_name']);
flash('archive_success', $staff['full_name'] . '\'s account was permanently deleted.');
redirect('../admin/pages/archive.php');