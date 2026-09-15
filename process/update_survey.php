<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../admin/pages/surveys.php');
}
$surveyId = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
if (!$surveyId) {
    redirect('../admin/pages/surveys.php');
}
$db = database();
$exists = $db->prepare('SELECT id, title, is_active, opens_at, closes_at FROM surveys WHERE id = ?');
$exists->execute([$surveyId]);
$survey = $exists->fetch();
if (!$survey) {
    flash('survey_error', 'Survey not found.');
    redirect('../admin/pages/surveys.php');
}
if (($_POST['action'] ?? '') === 'deactivate') {
    $deactivate = $db->prepare('UPDATE surveys SET is_active = 0 WHERE id = ?');
    $deactivate->execute([$surveyId]);
    log_activity($db, 'Updated', 'Survey', $survey['title']);
    flash('manage_survey_success', 'Survey was deactivated.');
    redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
}
if (($_POST['action'] ?? '') === 'reactivate') {
    // A survey can be reactivated two ways:
    //  - Quick reactivate (no dates in the request): only allowed while the
    //    survey's existing closing date hasn't passed yet, so "Active" still
    //    matches a real, still-open response window.
    //  - Reactivate with a new period (dates included in the request): always
    //    requires a fresh, still-valid window — used once the old closing
    //    date has already lapsed, or whenever the admin chooses to set one.
    $today = date('Y-m-d');
    $opensAt = trim((string) ($_POST['opens_at'] ?? ''));
    $closesAt = trim((string) ($_POST['closes_at'] ?? ''));
    $datesSubmitted = $opensAt !== '' || $closesAt !== '';

    if ($datesSubmitted) {
        $openingDate = DateTime::createFromFormat('!Y-m-d', $opensAt);
        $closingDate = DateTime::createFromFormat('!Y-m-d', $closesAt);
        $validDates = $openingDate &&
            $closingDate &&
            $openingDate->format('Y-m-d') === $opensAt &&
            $closingDate->format('Y-m-d') === $closesAt &&
            $opensAt >= $today &&
            $closingDate > $openingDate;

        if (!$validDates) {
            flash('manage_survey_error', 'The closing date must be later than the new opening date, and both dates must be today or later.');
            redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
        }

        $reactivate = $db->prepare('UPDATE surveys SET is_active = 1, opens_at = ?, closes_at = ? WHERE id = ?');
        $reactivate->execute([$opensAt, $closesAt, $surveyId]);
        log_activity($db, 'Updated', 'Survey', $survey['title']);
        flash('manage_survey_success', 'Survey was reactivated with a new response period: ' . date('M j, Y', strtotime($opensAt)) . ' - ' . date('M j, Y', strtotime($closesAt)) . '.');
        redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
    }

    if ($survey['closes_at'] <= $today) {
        flash('manage_survey_error', 'This survey\'s response period already ended. Set a new opening and closing date to reactivate it.');
        redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
    }

    $reactivate = $db->prepare('UPDATE surveys SET is_active = 1 WHERE id = ?');
    $reactivate->execute([$surveyId]);
    log_activity($db, 'Updated', 'Survey', $survey['title']);
    flash('manage_survey_success', 'Survey was reactivated using its current response period: ' . date('M j, Y', strtotime($survey['opens_at'])) . ' - ' . date('M j, Y', strtotime($survey['closes_at'])) . '.');
    redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
}
// A deactivated survey is kept as a read-only record until an administrator
// explicitly reactivates it from the management page.
if ((int) $survey['is_active'] !== 1) {
    flash('manage_survey_error', 'Reactivate this survey before editing it.');
    redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
}

// Once a survey has responses, its question structure is locked. Editing
// questions after submissions exist would orphan the answers already tied
// to those question IDs, silently destroying response data (see: rebuilding
// survey_questions from scratch on every save). Title, description, and
// dates can still be updated safely since they don't affect existing answers.
$submissionCheck = $db->prepare('SELECT COUNT(*) FROM survey_submissions WHERE survey_id = ?');
$submissionCheck->execute([$surveyId]);
$hasSubmissions = (int) $submissionCheck->fetchColumn() > 0;

$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$opensAt = (string) ($_POST['opens_at'] ?? '');
$closesAt = (string) ($_POST['closes_at'] ?? '');
$isActive = ($_POST['status'] ?? '1') === '1' ? 1 : 0;

if ($title === '' || $opensAt === '' || $closesAt === '' || $closesAt < $opensAt) {
    flash('manage_survey_error', 'Enter a title and valid opening and closing dates.');
    redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
}
if ($opensAt !== $survey['opens_at'] && $opensAt < date('Y-m-d')) {
    flash('manage_survey_error', 'The opening date cannot be in the past.');
    redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
}

if (!$hasSubmissions) {
    $texts = $_POST['question_text'] ?? [];
    $types = $_POST['question_type'] ?? [];
    $choices = $_POST['question_choices'] ?? [];
    $required = $_POST['question_required'] ?? [];
    $validTypes = ['multiple_choice', 'yes_no', 'rating', 'short_answer'];
    $questions = [];
    foreach ($texts as $index => $text) {
        $text = trim((string) $text);
        $type = (string) ($types[$index] ?? '');
        $choiceText = trim((string) ($choices[$index] ?? ''));
        if ($text === '' || !in_array($type, $validTypes, true)) {
            continue;
        }
        if ($type === 'multiple_choice' && $choiceText === '') {
            flash('manage_survey_error', 'Add at least one choice for every multiple-choice question.');
            redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
        }
        $questions[] = [$text, $type, $choiceText ?: null, isset($required[$index]) ? 1 : 0];
    }
    if (!$questions) {
        flash('manage_survey_error', 'Add at least one valid survey question.');
        redirect('../admin/pages/manage_survey.php?id=' . $surveyId);
    }
}

try {
    $db->beginTransaction();
    $updateSurvey = $db->prepare('UPDATE surveys SET title = ?, description = ?, opens_at = ?, closes_at = ?, is_active = ? WHERE id = ?');
    $updateSurvey->execute([$title, $description ?: 'No description provided.', $opensAt, $closesAt, $isActive, $surveyId]);

    if (!$hasSubmissions) {
        $db->prepare('DELETE FROM survey_questions WHERE survey_id = ?')->execute([$surveyId]);
        $insertQuestion = $db->prepare('INSERT INTO survey_questions (survey_id, question_text, question_type, choices_text, is_required) VALUES (?, ?, ?, ?, ?)');
        foreach ($questions as [$text, $type, $choiceText, $isRequired]) {
            $insertQuestion->execute([$surveyId, $text, $type, $choiceText, $isRequired]);
        }
    }

    $db->commit();
    log_activity($db, 'Updated', 'Survey', $title);
    flash('manage_survey_success', $hasSubmissions
        ? 'Survey details were updated. Questions are locked because this survey already has responses.'
        : 'Survey and questions were updated successfully.');
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    flash('manage_survey_error', 'The survey could not be updated.');
}
redirect('../admin/pages/manage_survey.php?id=' . $surveyId);