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
    // Safety net: permanent deletion is only allowed from the Archive, so a
    // survey must be archived first before it can be deleted for good.
    flash('survey_error', 'Archive this survey first before deleting it permanently.');
    redirect('../admin/pages/surveys.php');
}

$db->beginTransaction();
try {
    // No foreign-key CASCADE exists on this schema, so every related table
    // is cleaned up explicitly before the survey itself.
    $questionIds = $db->prepare('SELECT id FROM survey_questions WHERE survey_id = ?');
    $questionIds->execute([$surveyId]);
    $questionIds = $questionIds->fetchAll(PDO::FETCH_COLUMN);

    if ($questionIds) {
        $placeholders = implode(', ', array_fill(0, count($questionIds), '?'));
        $db->prepare("DELETE FROM survey_answers WHERE question_id IN ($placeholders)")->execute($questionIds);
    }

    $submissionIds = $db->prepare('SELECT id FROM survey_submissions WHERE survey_id = ?');
    $submissionIds->execute([$surveyId]);
    $submissionIds = $submissionIds->fetchAll(PDO::FETCH_COLUMN);

    if ($submissionIds) {
        $placeholders = implode(', ', array_fill(0, count($submissionIds), '?'));
        $db->prepare("DELETE FROM survey_answers WHERE submission_id IN ($placeholders)")->execute($submissionIds);
    }

    $db->prepare('DELETE FROM survey_submissions WHERE survey_id = ?')->execute([$surveyId]);
    $db->prepare('DELETE FROM survey_questions WHERE survey_id = ?')->execute([$surveyId]);
    $db->prepare('DELETE FROM surveys WHERE id = ?')->execute([$surveyId]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    flash('archive_error', 'The survey could not be deleted. Please try again.');
    redirect('../admin/pages/archive.php');
}

log_activity($db, 'Deleted', 'Survey', $survey['title']);
flash('archive_success', '"' . $survey['title'] . '" was permanently deleted, along with its questions, answers, and submissions.');
redirect('../admin/pages/archive.php');