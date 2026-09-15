<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/upload.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

try {
    $newPath = store_uploaded_image('photo', 'photos', 3 * 1024 * 1024);
} catch (RuntimeException $e) {
    flash('photo_error', $e->getMessage());
    redirect('../resident/profile.php');
}

if ($newPath === null) {
    flash('photo_error', 'Please choose a photo to upload.');
    redirect('../resident/profile.php');
}

$db = database();
$existingStmt = $db->prepare('SELECT photo_path FROM resident_profile WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$resident['id']]);
$oldPath = $existingStmt->fetchColumn();

// Upsert only the photo_path column so we never clobber personal-info fields
// managed elsewhere on this same table.
$statement = $db->prepare(
    'INSERT INTO resident_profile (resident_id, last_name, first_name, photo_path)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE photo_path = VALUES(photo_path)'
);
$statement->execute([$resident['id'], '', '', $newPath]);

if ($oldPath && $oldPath !== $newPath) {
    delete_uploaded_file($oldPath);
}

flash('photo_success', 'Profile photo updated.');
redirect('../resident/profile.php');
