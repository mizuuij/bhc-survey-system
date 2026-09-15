<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../config/upload.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/members.php');
}

$residentId = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
if (!$residentId) {
    redirect('../admin/pages/members.php');
}
$backTo = '../admin/pages/resident_view.php?id=' . $residentId;

$db = database();
$existingStmt = $db->prepare('SELECT photo_path FROM resident_profile WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$residentId]);
$oldPath = $existingStmt->fetchColumn();

if (!$oldPath) {
    flash('photo_success', 'No changes to save.');
    redirect($backTo);
}

$db->prepare('UPDATE resident_profile SET photo_path = NULL WHERE resident_id = ?')->execute([$residentId]);

delete_uploaded_file($oldPath);

log_activity($db, 'Deleted', 'Member', 'Member #' . $residentId);
flash('photo_success', 'Profile photo removed.');
redirect($backTo);
