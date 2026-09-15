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

$spouseName = trim($_POST['spouse_name'] ?? '');
$occupation = trim($_POST['spouse_occupation'] ?? '');
$employer = trim($_POST['spouse_employer'] ?? '');

$existingStmt = $db->prepare('SELECT spouse_name, occupation, employer FROM resident_spouse WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$residentId]);
$existing = $existingStmt->fetch();

if ($spouseName === '' && $occupation === '' && $employer === '') {
    if (!$existing) {
        flash('spouse_success', 'No changes to save.');
        redirect($backTo);
    }
    $db->prepare('DELETE FROM resident_spouse WHERE resident_id = ?')->execute([$residentId]);
    log_activity($db, 'Updated', 'Member', 'Member #' . $residentId);
    flash('spouse_success', 'Spouse information cleared.');
    redirect($backTo);
}

if ($spouseName === '') {
    flash('spouse_error', 'Spouse name is required.');
    redirect($backTo);
}
if (mb_strlen($spouseName) > 120 || mb_strlen($occupation) > 120 || mb_strlen($employer) > 120) {
    flash('spouse_error', 'Please keep each field under 120 characters.');
    redirect($backTo);
}

if ($existing
    && $existing['spouse_name'] === $spouseName
    && (string) $existing['occupation'] === $occupation
    && (string) $existing['employer'] === $employer
) {
    flash('spouse_success', 'No changes to save.');
    redirect($backTo);
}

$statement = $db->prepare(
    'INSERT INTO resident_spouse (resident_id, spouse_name, occupation, employer)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE spouse_name = VALUES(spouse_name), occupation = VALUES(occupation), employer = VALUES(employer)'
);
$statement->execute([
    $residentId,
    $spouseName,
    $occupation !== '' ? $occupation : null,
    $employer !== '' ? $employer : null,
]);
log_activity($db, 'Updated', 'Member', 'Member #' . $residentId);

flash('spouse_success', 'Spouse information saved.');
redirect($backTo);
