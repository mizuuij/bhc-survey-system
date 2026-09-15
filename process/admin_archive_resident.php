<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/members.php');
}

$residentId = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
if (!$residentId) {
    redirect('../admin/pages/members.php');
}

$allowedReturnTo = ['members.php', 'member_reports.php'];
$returnToParam = (string) ($_POST['return_to'] ?? '');
$returnTo = in_array($returnToParam, $allowedReturnTo, true) ? $returnToParam : 'members.php';

$db = database();
$statement = $db->prepare('SELECT head_name, archived_at FROM residents WHERE id = ?');
$statement->execute([$residentId]);
$resident = $statement->fetch();

if (!$resident) {
    flash('member_error', 'That resident record could not be found.');
    redirect('../admin/pages/' . $returnTo);
}

if ($resident['archived_at'] !== null) {
    // Already archived — nothing to do.
    redirect('../admin/pages/' . $returnTo);
}

// Archiving also deactivates the account so it can no longer be used to log
// in or answer surveys while it sits in the archive.
$update = $db->prepare('UPDATE residents SET archived_at = NOW(), is_active = 0 WHERE id = ?');
$update->execute([$residentId]);
log_activity($db, 'Archived', 'Member', $resident['head_name']);

flash('member_success', $resident['head_name'] . '\'s record was moved to the archive. You can restore it or delete it permanently from there.');
redirect('../admin/pages/' . $returnTo);
