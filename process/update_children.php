<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

$names = $_POST['child_name'] ?? [];
$sexes = $_POST['child_sex'] ?? [];
$ages = $_POST['child_age'] ?? [];
if (!is_array($names)) {
    $names = [];
}
if (!is_array($sexes)) {
    $sexes = [];
}
if (!is_array($ages)) {
    $ages = [];
}

$rows = [];
$count = max(count($names), count($sexes), count($ages));
for ($i = 0; $i < $count; $i++) {
    $name = trim((string) ($names[$i] ?? ''));
    $sex = trim((string) ($sexes[$i] ?? ''));
    $age = trim((string) ($ages[$i] ?? ''));

    if ($name === '' && $sex === '' && $age === '') {
        continue; // blank row, skip silently
    }
    if ($name === '' || $sex === '' || $age === '') {
        flash('children_error', 'Each child needs a name, sex, and age. Remove any empty row you don\'t need.');
        redirect('../resident/profile.php');
    }
    if (mb_strlen($name) > 120) {
        flash('children_error', 'Child name must be under 120 characters.');
        redirect('../resident/profile.php');
    }
    if (!in_array($sex, ['male', 'female'], true)) {
        flash('children_error', 'Please select a valid sex for each child.');
        redirect('../resident/profile.php');
    }
    if (!preg_match('/^\d+$/', $age) || (int) $age > 120) {
        flash('children_error', 'Age must be a valid number (0–120).');
        redirect('../resident/profile.php');
    }

    $rows[] = [$name, $sex, (int) $age];
}

$db = database();

$existingStmt = $db->prepare('SELECT child_name, sex, age FROM resident_children WHERE resident_id = ? ORDER BY id ASC');
$existingStmt->execute([$resident['id']]);
$existingRows = array_map(
    static fn ($row) => [$row['child_name'], $row['sex'], (int) $row['age']],
    $existingStmt->fetchAll()
);

if ($existingRows === $rows) {
    flash('children_success', 'No changes to save.');
    redirect('../resident/profile.php');
}

$db->beginTransaction();
try {
    $delete = $db->prepare('DELETE FROM resident_children WHERE resident_id = ?');
    $delete->execute([$resident['id']]);

    if ($rows) {
        $insert = $db->prepare('INSERT INTO resident_children (resident_id, child_name, sex, age) VALUES (?, ?, ?, ?)');
        foreach ($rows as [$name, $sex, $age]) {
            $insert->execute([$resident['id'], $name, $sex, $age]);
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    flash('children_error', 'Something went wrong while saving. Please try again.');
    redirect('../resident/profile.php');
}

flash('children_success', $rows ? 'Children information saved.' : 'Children list cleared.');
redirect('../resident/profile.php');
