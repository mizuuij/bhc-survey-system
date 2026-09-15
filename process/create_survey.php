<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../admin/pages/surveys.php');
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$opensAt = $_POST['opens_at'] ?? '';
$closesAt = $_POST['closes_at'] ?? '';
$isActive = ($_POST['status'] ?? '1') === '1' ? 1 : 0;
$texts = $_POST['question_text'] ?? [];
$types = $_POST['question_type'] ?? [];
$choices = $_POST['question_choices'] ?? [];
$required = $_POST['question_required'] ?? [];

if ($title === '' || $opensAt === '' || $closesAt === '' || $closesAt < $opensAt) { flash('survey_error', 'Enter a title and valid opening and closing dates.'); redirect('../admin/pages/surveys.php'); }
if ($opensAt < date('Y-m-d')) { flash('survey_error', 'The opening date cannot be in the past.'); redirect('../admin/pages/surveys.php'); }
$questions = [];
foreach ($texts as $index => $text) {
    $text = trim((string) $text);
    $type = $types[$index] ?? '';
    $choiceText = trim((string) ($choices[$index] ?? ''));
    if ($text === '' || !in_array($type, ['multiple_choice','yes_no','rating','short_answer'], true)) {
        continue;
    }
    if ($type === 'multiple_choice' && $choiceText === '') {
        flash('survey_error', 'Add at least one choice for every multiple-choice question.');
        redirect('../admin/pages/surveys.php');
    }
    $questions[] = [$text, $type, $choiceText, isset($required[$index]) ? 1 : 0];
}
if (!$questions) { flash('survey_error', 'Add at least one survey question.'); redirect('../admin/pages/surveys.php'); }

$db = database();
try {
    $db->beginTransaction();
    $survey = $db->prepare('INSERT INTO surveys (title, description, opens_at, closes_at, is_active) VALUES (?, ?, ?, ?, ?)');
    $survey->execute([$title, $description ?: 'No description provided.', $opensAt, $closesAt, $isActive]);
    $surveyId = (int) $db->lastInsertId();
    $question = $db->prepare('INSERT INTO survey_questions (survey_id, question_text, question_type, choices_text, is_required) VALUES (?, ?, ?, ?, ?)');
    foreach ($questions as [$text, $type, $choiceText, $isRequired]) $question->execute([$surveyId, $text, $type, $choiceText ?: null, $isRequired]);
    $db->commit();
    log_activity($db, 'Created', 'Survey', $title);
    flash('survey_success', 'Survey and questions were created successfully.');
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    flash('survey_error', 'The survey could not be saved.');
}
redirect('../admin/pages/surveys.php');
