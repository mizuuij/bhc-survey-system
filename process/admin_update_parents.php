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
$backTo = '../admin/pages/resident_view.php?id=' . $residentId;

$db = database();
$existsStmt = $db->prepare('SELECT id FROM residents WHERE id = ?');
$existsStmt->execute([$residentId]);
if (!$existsStmt->fetch()) {
    flash('member_error', 'That resident record could not be found.');
    redirect('../admin/pages/members.php');
}

$fatherName = trim($_POST['father_name'] ?? '');
$motherName = trim($_POST['mother_name'] ?? '');

if ($fatherName === '' || $motherName === '') {
    flash('parents_error', "Please provide both the father's and mother's name.");
    redirect($backTo);
}
if (mb_strlen($fatherName) > 120 || mb_strlen($motherName) > 120) {
    flash('parents_error', 'Please keep each name under 120 characters.');
    redirect($backTo);
}

$existingStmt = $db->prepare('SELECT father_name, mother_name FROM resident_parents WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$residentId]);
$existing = $existingStmt->fetch();

if ($existing && $existing['father_name'] === $fatherName && $existing['mother_name'] === $motherName) {
    flash('parents_success', 'No changes to save.');
    redirect($backTo);
}

$statement = $db->prepare(
    'INSERT INTO resident_parents (resident_id, father_name, mother_name)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE father_name = VALUES(father_name), mother_name = VALUES(mother_name)'
);
$statement->execute([$residentId, $fatherName, $motherName]);
log_activity($db, 'Updated', 'Member', 'Member #' . $residentId);

flash('parents_success', "Parents' information saved.");
redirect($backTo);
