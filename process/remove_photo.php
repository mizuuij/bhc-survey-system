<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/upload.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

$db = database();
$existingStmt = $db->prepare('SELECT photo_path FROM resident_profile WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$resident['id']]);
$oldPath = $existingStmt->fetchColumn();

if (!$oldPath) {
    flash('photo_success', 'No changes to save.');
    redirect('../resident/profile.php');
}

$statement = $db->prepare('UPDATE resident_profile SET photo_path = NULL WHERE resident_id = ?');
$statement->execute([$resident['id']]);

delete_uploaded_file($oldPath);

flash('photo_success', 'Profile photo removed.');
redirect('../resident/profile.php');
