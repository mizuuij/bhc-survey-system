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
$existsStmt = $db->prepare('SELECT id FROM residents WHERE id = ?');
$existsStmt->execute([$residentId]);
if (!$existsStmt->fetch()) {
    flash('member_error', 'That resident record could not be found.');
    redirect('../admin/pages/members.php');
}

try {
    $newPath = store_uploaded_image('photo', 'photos', 3 * 1024 * 1024);
} catch (RuntimeException $e) {
    flash('photo_error', $e->getMessage());
    redirect($backTo);
}

if ($newPath === null) {
    flash('photo_error', 'Please choose a photo to upload.');
    redirect($backTo);
}

$existingStmt = $db->prepare('SELECT photo_path FROM resident_profile WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$residentId]);
$oldPath = $existingStmt->fetchColumn();

$statement = $db->prepare(
    'INSERT INTO resident_profile (resident_id, last_name, first_name, photo_path)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE photo_path = VALUES(photo_path)'
);
$statement->execute([$residentId, '', '', $newPath]);

if ($oldPath && $oldPath !== $newPath) {
    delete_uploaded_file($oldPath);
}

log_activity($db, 'Updated', 'Member', 'Member #' . $residentId);
flash('photo_success', 'Profile photo updated.');
redirect($backTo);
