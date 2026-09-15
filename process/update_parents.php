<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

$fatherName = trim($_POST['father_name'] ?? '');
$motherName = trim($_POST['mother_name'] ?? '');

if ($fatherName === '' || $motherName === '') {
    flash('parents_error', "Please provide both your father's and mother's name.");
    redirect('../resident/profile.php');
}
if (mb_strlen($fatherName) > 120 || mb_strlen($motherName) > 120) {
    flash('parents_error', 'Please keep each name under 120 characters.');
    redirect('../resident/profile.php');
}

$existingStmt = database()->prepare('SELECT father_name, mother_name FROM resident_parents WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$resident['id']]);
$existing = $existingStmt->fetch();

if ($existing && $existing['father_name'] === $fatherName && $existing['mother_name'] === $motherName) {
    flash('parents_success', 'No changes to save.');
    redirect('../resident/profile.php');
}

$statement = database()->prepare(
    'INSERT INTO resident_parents (resident_id, father_name, mother_name)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE father_name = VALUES(father_name), mother_name = VALUES(mother_name)'
);
$statement->execute([$resident['id'], $fatherName, $motherName]);

flash('parents_success', "Parents' information saved.");
redirect('../resident/profile.php');
