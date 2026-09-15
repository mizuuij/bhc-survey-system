<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

$allowedReturns = ['surveys.php', 'reports.php'];
$returnTo = (string) ($_POST['return_to'] ?? 'surveys.php');
if (!in_array($returnTo, $allowedReturns, true)) {
    $returnTo = 'surveys.php';
}
$redirectTarget = '../admin/pages/' . $returnTo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($redirectTarget);
}

$surveyId = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
if (!$surveyId) {
    redirect($redirectTarget);
}

$db = database();
$statement = $db->prepare('SELECT title, archived_at FROM surveys WHERE id = ?');
$statement->execute([$surveyId]);
$survey = $statement->fetch();

if (!$survey) {
    flash('survey_error', 'That survey could not be found.');
    redirect($redirectTarget);
}

if ($survey['archived_at'] !== null) {
    // Already archived — nothing to do.
    redirect($redirectTarget);
}

// Archiving also closes the survey so residents can no longer answer it
// while it sits in the archive.
$update = $db->prepare('UPDATE surveys SET archived_at = NOW(), is_active = 0 WHERE id = ?');
$update->execute([$surveyId]);
log_activity($db, 'Archived', 'Survey', $survey['title']);

flash('survey_success', '"' . $survey['title'] . '" was moved to the archive. You can restore it or delete it permanently from there.');
redirect($redirectTarget);