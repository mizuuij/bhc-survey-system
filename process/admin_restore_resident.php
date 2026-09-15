<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/archive.php');
}

$residentId = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
if (!$residentId) {
    redirect('../admin/pages/archive.php');
}

$db = database();
$statement = $db->prepare('SELECT head_name, archived_at FROM residents WHERE id = ?');
$statement->execute([$residentId]);
$resident = $statement->fetch();

if (!$resident) {
    flash('archive_error', 'That resident record could not be found.');
    redirect('../admin/pages/archive.php');
}

if ($resident['archived_at'] === null) {
    // Not archived — nothing to restore.
    redirect('../admin/pages/archive.php');
}

// Restored accounts stay deactivated until the admin explicitly re-activates
// them from Member Management, so nobody regains login access by accident.
$update = $db->prepare('UPDATE residents SET archived_at = NULL WHERE id = ?');
$update->execute([$residentId]);
log_activity($db, 'Updated', 'Member', $resident['head_name']);

flash('member_success', $resident['head_name'] . '\'s record was restored to Member Management.');
redirect('../admin/pages/archive.php');
