<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/archive.php');
}

$surveyId = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
if (!$surveyId) {
    redirect('../admin/pages/archive.php');
}

$db = database();
$statement = $db->prepare('SELECT title, archived_at FROM surveys WHERE id = ?');
$statement->execute([$surveyId]);
$survey = $statement->fetch();

if (!$survey) {
    flash('archive_error', 'That survey could not be found.');
    redirect('../admin/pages/archive.php');
}

if ($survey['archived_at'] === null) {
    // Not archived — nothing to restore.
    redirect('../admin/pages/archive.php');
}

// Restored surveys stay closed until the admin explicitly reactivates them
// from Survey Management, so nothing reopens to residents by accident.
$update = $db->prepare('UPDATE surveys SET archived_at = NULL WHERE id = ?');
$update->execute([$surveyId]);
log_activity($db, 'Updated', 'Survey', $survey['title']);

flash('archive_success', '"' . $survey['title'] . '" was restored to Survey Management.');
redirect('../admin/pages/archive.php');