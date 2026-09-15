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

$householdNumber = trim((string) ($_POST['household_number'] ?? ''));
$contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$purok = trim((string) ($_POST['purok'] ?? ''));

$lastName = trim($_POST['last_name'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$extensionName = trim($_POST['extension_name'] ?? '');
$sex = trim($_POST['sex'] ?? '');
$civilStatus = trim($_POST['civil_status'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$occupation = trim($_POST['occupation'] ?? '');
$employer = trim($_POST['employer'] ?? '');
$employerAddress = trim($_POST['employer_address'] ?? '');

$allowedCivilStatus = ['single', 'married', 'widowed', 'separated', 'divorced'];
$allowedPuroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];

if ($lastName === '' || $firstName === '' || $householdNumber === '' || $contactNumber === '' || $address === '') {
    flash('personal_error', 'Please complete all required fields.');
    redirect($backTo);
}
if (!in_array($civilStatus, $allowedCivilStatus, true)) {
    flash('personal_error', 'Please select a valid civil status.');
    redirect($backTo);
}
if (!in_array($sex, ['male', 'female'], true)) {
    flash('personal_error', 'Please select a valid sex.');
    redirect($backTo);
}
if ($purok !== '' && !in_array($purok, $allowedPuroks, true)) {
    flash('personal_error', 'Please select a valid purok.');
    redirect($backTo);
}
if ($birthday === '' || !DateTime::createFromFormat('!Y-m-d', $birthday) || (int) substr($birthday, 0, 4) < 1900 || (int) substr($birthday, 0, 4) > (int) date('Y')) {
    flash('personal_error', 'Please provide a valid Birth of Date.');
    redirect($backTo);
}
if ($birthday > date('Y-m-d')) {
    flash('personal_error', 'Birth of Date cannot be in the future.');
    redirect($backTo);
}
if (mb_strlen($lastName) > 80 || mb_strlen($firstName) > 80 || mb_strlen($middleName) > 80) {
    flash('personal_error', 'Please keep names under 80 characters.');
    redirect($backTo);
}
if (mb_strlen($extensionName) > 20) {
    flash('personal_error', 'Extension name must be under 20 characters.');
    redirect($backTo);
}
if (mb_strlen($occupation) > 120 || mb_strlen($employer) > 120) {
    flash('personal_error', 'Please keep occupation and employer under 120 characters.');
    redirect($backTo);
}
if (!preg_match('/^\d+$/', $contactNumber) || strlen($contactNumber) <= 10 || strlen($contactNumber) > 11) {
    flash('personal_error', 'Contact number must be a valid 11-digit phone number.');
    redirect($backTo);
}

// Head of Household is just this resident's own name, the same way it's
// derived on the Add Member form — no input of its own on this form.
$headName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName, $extensionName], fn($part) => $part !== '')));

$existingStmt = $db->prepare('SELECT last_name, first_name, middle_name, extension_name, sex, civil_status, birthday, occupation, employer, employer_address FROM resident_profile WHERE resident_id = ? LIMIT 1');
$existingStmt->execute([$residentId]);
$existing = $existingStmt->fetch();

$new = [
    'last_name' => $lastName,
    'first_name' => $firstName,
    'middle_name' => $middleName !== '' ? $middleName : null,
    'extension_name' => $extensionName !== '' ? $extensionName : null,
    'sex' => $sex,
    'civil_status' => $civilStatus,
    'birthday' => $birthday,
    'occupation' => $occupation !== '' ? $occupation : null,
    'employer' => $employer !== '' ? $employer : null,
    'employer_address' => $employerAddress !== '' ? $employerAddress : null,
];

$profileChanged = true;
if ($existing) {
    $profileChanged = false;
    foreach ($new as $key => $value) {
        if ((string) ($existing[$key] ?? '') !== (string) ($value ?? '')) {
            $profileChanged = true;
            break;
        }
    }
}

$residentStmt = $db->prepare('SELECT household_number, head_name, contact_number, address, purok FROM residents WHERE id = ?');
$residentStmt->execute([$residentId]);
$existingResident = $residentStmt->fetch();
$residentChanged = $householdNumber !== trim((string) $existingResident['household_number'])
    || $headName !== trim((string) $existingResident['head_name'])
    || $contactNumber !== trim((string) $existingResident['contact_number'])
    || $address !== trim((string) $existingResident['address'])
    || $purok !== trim((string) ($existingResident['purok'] ?? ''));

if (!$profileChanged && !$residentChanged) {
    flash('personal_success', 'No changes to save.');
    redirect($backTo);
}

try {
    $db->beginTransaction();

    if ($residentChanged) {
        $update = $db->prepare('UPDATE residents SET household_number = ?, head_name = ?, contact_number = ?, address = ?, purok = ? WHERE id = ?');
        $update->execute([$householdNumber, $headName, $contactNumber, $address, $purok !== '' ? $purok : null, $residentId]);
    }

    if ($profileChanged) {
        $statement = $db->prepare(
            'INSERT INTO resident_profile (resident_id, last_name, first_name, middle_name, extension_name, sex, civil_status, birthday, occupation, employer, employer_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_name = VALUES(last_name),
                first_name = VALUES(first_name),
                middle_name = VALUES(middle_name),
                extension_name = VALUES(extension_name),
                sex = VALUES(sex),
                civil_status = VALUES(civil_status),
                birthday = VALUES(birthday),
                occupation = VALUES(occupation),
                employer = VALUES(employer),
                employer_address = VALUES(employer_address)'
        );
        $statement->execute([
            $residentId,
            $new['last_name'],
            $new['first_name'],
            $new['middle_name'],
            $new['extension_name'],
            $new['sex'],
            $new['civil_status'],
            $new['birthday'],
            $new['occupation'],
            $new['employer'],
            $new['employer_address'],
        ]);
    }

    $db->commit();
    log_activity($db, 'Updated', 'Member', $headName);
    flash('personal_success', 'Personal information saved.');
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $message = str_contains($exception->getMessage(), 'household_number')
        ? 'That household number is already in use.'
        : 'The member could not be updated.';
    flash('personal_error', $message);
}

redirect($backTo);
