<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

$spouseName = trim($_POST['spouse_name'] ?? '');
$occupation = trim($_POST['spouse_occupation'] ?? '');
$employer = trim($_POST['spouse_employer'] ?? '');

$existingStmt = database()->prepare('SELECT spouse_name, occupation, employer FROM resident_spouse WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$resident['id']]);
$existing = $existingStmt->fetch();

// Fully blank submission clears any previously saved spouse info (e.g. resident is not married).
if ($spouseName === '' && $occupation === '' && $employer === '') {
    if (!$existing) {
        flash('spouse_success', 'No changes to save.');
        redirect('../resident/profile.php');
    }
    $statement = database()->prepare('DELETE FROM resident_spouse WHERE resident_id = ?');
    $statement->execute([$resident['id']]);
    flash('spouse_success', 'Spouse information cleared.');
    redirect('../resident/profile.php');
}

if ($spouseName === '') {
    flash('spouse_error', 'Spouse name is required.');
    redirect('../resident/profile.php');
}
if (mb_strlen($spouseName) > 120 || mb_strlen($occupation) > 120 || mb_strlen($employer) > 120) {
    flash('spouse_error', 'Please keep each field under 120 characters.');
    redirect('../resident/profile.php');
}

if ($existing
    && $existing['spouse_name'] === $spouseName
    && (string) $existing['occupation'] === $occupation
    && (string) $existing['employer'] === $employer
) {
    flash('spouse_success', 'No changes to save.');
    redirect('../resident/profile.php');
}

$statement = database()->prepare(
    'INSERT INTO resident_spouse (resident_id, spouse_name, occupation, employer)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE spouse_name = VALUES(spouse_name), occupation = VALUES(occupation), employer = VALUES(employer)'
);
$statement->execute([
    $resident['id'],
    $spouseName,
    $occupation !== '' ? $occupation : null,
    $employer !== '' ? $employer : null,
]);

flash('spouse_success', 'Spouse information saved.');
redirect('../resident/profile.php');
