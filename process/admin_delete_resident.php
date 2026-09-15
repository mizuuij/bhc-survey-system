<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../config/upload.php';
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
    // Safety net: permanent deletion is only allowed from the Archive, so a
    // record must be archived first before it can be deleted for good.
    flash('member_error', 'Archive this record first before deleting it permanently.');
    redirect('../admin/pages/members.php');
}

// Collect uploaded file paths before the rows are deleted.
$filesToDelete = [];

$photoStmt = $db->prepare('SELECT photo_path FROM resident_profile WHERE resident_id = ?');
$photoStmt->execute([$residentId]);
$photoPath = $photoStmt->fetchColumn();
if ($photoPath) {
    $filesToDelete[] = $photoPath;
}

$signaturesStmt = $db->prepare('SELECT signature_path FROM resident_references WHERE resident_id = ? AND signature_path IS NOT NULL');
$signaturesStmt->execute([$residentId]);
foreach ($signaturesStmt->fetchAll(PDO::FETCH_COLUMN) as $signaturePath) {
    $filesToDelete[] = $signaturePath;
}

$db->beginTransaction();
try {
    // No foreign-key CASCADE exists on this schema, so each related table
    // is cleaned up explicitly before the household account itself.
    $db->prepare('DELETE FROM resident_children WHERE resident_id = ?')->execute([$residentId]);
    $db->prepare('DELETE FROM resident_references WHERE resident_id = ?')->execute([$residentId]);
    $db->prepare('DELETE FROM resident_spouse WHERE resident_id = ?')->execute([$residentId]);
    $db->prepare('DELETE FROM resident_parents WHERE resident_id = ?')->execute([$residentId]);
    $db->prepare('DELETE FROM resident_profile WHERE resident_id = ?')->execute([$residentId]);
    $db->prepare('DELETE FROM residents WHERE id = ?')->execute([$residentId]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    flash('archive_error', 'The resident record could not be deleted. Please try again.');
    redirect('../admin/pages/archive.php');
}

foreach ($filesToDelete as $path) {
    delete_uploaded_file($path);
}

log_activity($db, 'Deleted', 'Member', $resident['head_name']);
flash('archive_success', $resident['head_name'] . '\'s record was permanently deleted.');
redirect('../admin/pages/archive.php');
