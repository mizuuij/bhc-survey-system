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
        continue;
    }
    if ($name === '' || $sex === '' || $age === '') {
        flash('children_error', 'Each child needs a name, sex, and age. Remove any empty row you don\'t need.');
        redirect($backTo);
    }
    if (mb_strlen($name) > 120) {
        flash('children_error', 'Child name must be under 120 characters.');
        redirect($backTo);
    }
    if (!in_array($sex, ['male', 'female'], true)) {
        flash('children_error', 'Please select a valid sex for each child.');
        redirect($backTo);
    }
    if (!preg_match('/^\d+$/', $age) || (int) $age > 120) {
        flash('children_error', 'Age must be a valid number (0–120).');
        redirect($backTo);
    }

    $rows[] = [$name, $sex, (int) $age];
}

$existingStmt = $db->prepare('SELECT child_name, sex, age FROM resident_children WHERE resident_id = ? ORDER BY id ASC');
$existingStmt->execute([$residentId]);
$existingRows = array_map(
    static fn ($row) => [$row['child_name'], $row['sex'], (int) $row['age']],
    $existingStmt->fetchAll()
);

if ($existingRows === $rows) {
    flash('children_success', 'No changes to save.');
    redirect($backTo);
}

$db->beginTransaction();
try {
    $db->prepare('DELETE FROM resident_children WHERE resident_id = ?')->execute([$residentId]);

    if ($rows) {
        $insert = $db->prepare('INSERT INTO resident_children (resident_id, child_name, sex, age) VALUES (?, ?, ?, ?)');
        foreach ($rows as [$name, $sex, $age]) {
            $insert->execute([$residentId, $name, $sex, $age]);
        }
    }
    $db->commit();
    log_activity($db, 'Updated', 'Member', 'Member #' . $residentId);
} catch (Throwable $e) {
    $db->rollBack();
    flash('children_error', 'Something went wrong while saving. Please try again.');
    redirect($backTo);
}

flash('children_success', $rows ? 'Children information saved.' : 'Children list cleared.');
redirect($backTo);
