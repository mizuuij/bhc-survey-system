<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/profile.php');
}

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
$contactNumber = trim($_POST['contact_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$purok = trim($_POST['purok'] ?? '');

$allowedCivilStatus = ['single', 'married', 'widowed', 'separated', 'divorced'];
$allowedPuroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'];

if ($lastName === '' || $firstName === '' || $contactNumber === '' || $address === '') {
    flash('profile_error', 'Please complete all required fields.');
    redirect('../resident/profile.php');
}
if (!in_array($civilStatus, $allowedCivilStatus, true)) {
    flash('profile_error', 'Please select a valid civil status.');
    redirect('../resident/profile.php');
}
if (!in_array($sex, ['male', 'female'], true)) {
    flash('profile_error', 'Please select a valid sex.');
    redirect('../resident/profile.php');
}
if ($purok !== '' && !in_array($purok, $allowedPuroks, true)) {
    flash('profile_error', 'Please select a valid purok.');
    redirect('../resident/profile.php');
}
if ($birthday === '' || !DateTime::createFromFormat('!Y-m-d', $birthday) || (int) substr($birthday, 0, 4) < 1900 || (int) substr($birthday, 0, 4) > (int) date('Y')) {
    flash('profile_error', 'Please provide a valid Birth of Date.');
    redirect('../resident/profile.php');
}
if ($birthday > date('Y-m-d')) {
    flash('profile_error', 'Birth of Date cannot be in the future.');
    redirect('../resident/profile.php');
}
if (mb_strlen($lastName) > 80 || mb_strlen($firstName) > 80 || mb_strlen($middleName) > 80) {
    flash('profile_error', 'Please keep names under 80 characters.');
    redirect('../resident/profile.php');
}
if (mb_strlen($extensionName) > 20) {
    flash('profile_error', 'Extension name must be under 20 characters.');
    redirect('../resident/profile.php');
}
if (mb_strlen($occupation) > 120 || mb_strlen($employer) > 120) {
    flash('profile_error', 'Please keep occupation and employer under 120 characters.');
    redirect('../resident/profile.php');
}
if (!preg_match('/^\d+$/', $contactNumber) || strlen($contactNumber) <= 10 || strlen($contactNumber) > 11) {
    flash('profile_error', 'Contact number must be a valid 11-digit phone number.');
    redirect('../resident/profile.php');
}

// head_name has no input of its own on this form — it's kept in sync with
// whatever name the resident enters here, the same way it's shown in the
// sidebar as "Household Head".
$headName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName, $extensionName], fn($part) => $part !== '')));

$db = database();

$profileStmt = $db->prepare('SELECT last_name, first_name, middle_name, extension_name, sex, civil_status, birthday, occupation, employer, employer_address FROM resident_profile WHERE resident_id = ? LIMIT 1');
$profileStmt->execute([$resident['id']]);
$existingProfile = $profileStmt->fetch();

$newProfile = [
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

$profileChanged = false;
if (!$existingProfile) {
    $profileChanged = true;
} else {
    foreach ($newProfile as $key => $value) {
        if ((string) ($existingProfile[$key] ?? '') !== (string) ($value ?? '')) {
            $profileChanged = true;
            break;
        }
    }
}

$residentChanged = $headName !== trim((string) $resident['head_name'])
    || $contactNumber !== trim((string) $resident['contact_number'])
    || $address !== trim((string) $resident['address'])
    || $purok !== trim((string) ($resident['purok'] ?? ''));

if (!$profileChanged && !$residentChanged) {
    flash('profile_success', 'No changes to save.');
    redirect('../resident/profile.php');
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
        $resident['id'],
        $newProfile['last_name'],
        $newProfile['first_name'],
        $newProfile['middle_name'],
        $newProfile['extension_name'],
        $newProfile['sex'],
        $newProfile['civil_status'],
        $newProfile['birthday'],
        $newProfile['occupation'],
        $newProfile['employer'],
        $newProfile['employer_address'],
    ]);
}

if ($residentChanged) {
    $statement = $db->prepare('UPDATE residents SET head_name = ?, contact_number = ?, address = ?, purok = ? WHERE id = ?');
    $statement->execute([$headName, $contactNumber, $address, $purok !== '' ? $purok : null, $resident['id']]);
}

flash('profile_success', 'Profile updated successfully.');
redirect('../resident/profile.php');