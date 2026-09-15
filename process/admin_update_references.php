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

$existingStmt = $db->prepare('SELECT id, reference_name, signature_path FROM resident_references WHERE resident_id = ? ORDER BY id ASC LIMIT 2');
$existingStmt->execute([$residentId]);
$existing = $existingStmt->fetchAll();

$slots = [];
$filesToDelete = [];

for ($i = 1; $i <= 2; $i++) {
    $name = trim((string) ($_POST['reference_name_' . $i] ?? ''));
    if ($name === '') {
        flash('references_error', 'Please provide a name for both character references.');
        redirect($backTo);
    }
    if (mb_strlen($name) > 120) {
        flash('references_error', 'Reference name must be under 120 characters.');
        redirect($backTo);
    }

    $currentPath = $existing[$i - 1]['signature_path'] ?? null;
    $removeExisting = isset($_POST['remove_signature_' . $i]);

    try {
        $newPath = store_uploaded_image('signature_' . $i, 'signatures', 2 * 1024 * 1024);
    } catch (RuntimeException $e) {
        flash('references_error', 'Reference ' . $i . ': ' . $e->getMessage());
        redirect($backTo);
    }

    if ($newPath !== null) {
        if ($currentPath) {
            $filesToDelete[] = $currentPath;
        }
        $finalPath = $newPath;
    } elseif ($removeExisting) {
        if ($currentPath) {
            $filesToDelete[] = $currentPath;
        }
        $finalPath = null;
    } else {
        $finalPath = $currentPath;
    }

    $slots[] = [$name, $finalPath];
}

$existingSlots = [];
for ($i = 0; $i < 2; $i++) {
    $existingSlots[] = [
        $existing[$i]['reference_name'] ?? null,
        $existing[$i]['signature_path'] ?? null,
    ];
}

if ($existingSlots === $slots) {
    flash('references_success', 'No changes to save.');
    redirect($backTo);
}

$db->beginTransaction();
try {
    $db->prepare('DELETE FROM resident_references WHERE resident_id = ?')->execute([$residentId]);

    $insert = $db->prepare('INSERT INTO resident_references (resident_id, reference_name, signature_path) VALUES (?, ?, ?)');
    foreach ($slots as [$name, $path]) {
        $insert->execute([$residentId, $name, $path]);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    flash('references_error', 'Something went wrong while saving. Please try again.');
    redirect($backTo);
}

foreach ($filesToDelete as $path) {
    delete_uploaded_file($path);
}

log_activity($db, 'Updated', 'Member', 'Member #' . $residentId);
flash('references_success', 'Character references saved.');
redirect($backTo);
