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

$db = database();
$exists = $db->prepare('SELECT id, resident_number, head_name, is_active FROM residents WHERE id = ?');
$exists->execute([$residentId]);
$resident = $exists->fetch();
if (!$resident) {
    flash('member_error', 'Member not found.');
    redirect('../admin/pages/members.php');
}

$action = (string) ($_POST['action'] ?? 'save');

$returnTo = (string) ($_POST['return_to'] ?? '');
$redirectTarget = (str_starts_with($returnTo, 'resident_view.php?id=') && (int) substr($returnTo, 22) === $residentId)
    ? '../admin/pages/' . $returnTo
    : '../admin/pages/members.php';

if ($action === 'toggle') {
    $newStatus = ((int) $resident['is_active']) === 1 ? 0 : 1;
    $db->prepare('UPDATE residents SET is_active = ? WHERE id = ?')->execute([$newStatus, $residentId]);
    log_activity($db, 'Updated', 'Member', $resident['head_name']);
    flash('member_success', $newStatus ? 'Member account activated.' : 'Member account deactivated.');
    redirect($redirectTarget);
}

if ($action === 'reset_password') {
    $temporaryPassword = $resident['resident_number'];
    $db->prepare('UPDATE residents SET password_hash = ?, must_change_password = 1 WHERE id = ?')
        ->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $residentId]);
    flash('member_success', "Password reset to the default (resident number): {$temporaryPassword}");
    redirect($redirectTarget);
}

$headName = trim((string) ($_POST['head_name'] ?? ''));
$contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$householdNumber = trim((string) ($_POST['household_number'] ?? ''));

$allowedPuroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];

// The quick-edit modal on Member Management doesn't have a Purok field yet,
// so only touch `purok` when the submitting form actually sent it (the
// full record page always sends it, even as an empty string). This keeps
// the quick-edit modal from silently clearing a resident's saved Purok.
$purokProvided = array_key_exists('purok', $_POST);
if ($purokProvided) {
    $purok = trim((string) $_POST['purok']);
    if ($purok !== '' && !in_array($purok, $allowedPuroks, true)) {
        flash('member_error', 'Please select a valid purok.');
        redirect($redirectTarget);
    }
    $purok = $purok !== '' ? $purok : null;
} else {
    $currentPurokStmt = $db->prepare('SELECT purok FROM residents WHERE id = ?');
    $currentPurokStmt->execute([$residentId]);
    $purok = $currentPurokStmt->fetchColumn();
    $purok = $purok !== false ? $purok : null;
}

if ($headName === '' || $contactNumber === '' || $address === '' || $householdNumber === '') {
    flash('member_error', 'All member fields are required.');
    redirect($redirectTarget);
}

if(!preg_match('/^\d+$/', $contactNumber) || strlen($contactNumber) <= 10 || strlen($contactNumber) > 11) {
    flash('member_error', 'Contact number must be a valid phone number and cannot exceed 11 digits.');
    redirect($redirectTarget);
}


try {
    $update = $db->prepare('UPDATE residents SET household_number = ?, head_name = ?, contact_number = ?, address = ?, purok = ? WHERE id = ?');
    $update->execute([$householdNumber, $headName, $contactNumber, $address, $purok, $residentId]);
    log_activity($db, 'Updated', 'Member', $headName);
    flash('member_success', 'Member details updated.');
} catch (Throwable $exception) {
    $message = str_contains($exception->getMessage(), 'household_number')
        ? 'That household number is already in use.'
        : 'The member could not be updated.';
    flash('member_error', $message);
}

redirect($redirectTarget);