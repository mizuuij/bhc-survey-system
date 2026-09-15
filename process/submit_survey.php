<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$resident = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../resident/surveyform.php');
}

$surveyId = (int) ($_POST['survey_id'] ?? 0);

$surveyStatement = database()->prepare('SELECT id, title FROM surveys WHERE id = ? AND is_active = 1 AND CURDATE() >= opens_at AND CURDATE() < closes_at LIMIT 1');
$surveyStatement->execute([$surveyId]);
$survey = $surveyStatement->fetch();
if (!$survey) {
    flash('survey_error', 'There is no active survey available right now.');
    redirect('../resident/surveys.php');
}

$questionsStatement = database()->prepare('SELECT id, question_type, choices_text, is_required FROM survey_questions WHERE survey_id = ? ORDER BY id ASC');
$questionsStatement->execute([$surveyId]);
$questions = $questionsStatement->fetchAll();

if (!$questions) {
    flash('survey_error', 'This survey has no questions yet.');
    redirect('../resident/surveys.php');
}

// Build one answer per question dynamically instead of assuming a fixed q1-q4
// shape, so any mix of question types/counts created in the survey builder
// is actually recorded.
$answers = [];
foreach ($questions as $question) {
    $field = 'question_' . $question['id'];
    $raw = $_POST[$field] ?? '';
    $isRequired = (bool) $question['is_required'];

    switch ($question['question_type']) {
        case 'multiple_choice':
            $choices = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['choices_text']))));
            $value = trim((string) $raw);
            if ($isRequired && !in_array($value, $choices, true)) {
                flash('survey_error', 'Please answer every required question.');
                redirect('../resident/surveyform.php?survey_id=' . $surveyId);
            }
            $answers[(int) $question['id']] = in_array($value, $choices, true) ? $value : null;
            break;

        case 'yes_no':
            $value = (string) $raw;
            if ($isRequired && !in_array($value, ['yes', 'no'], true)) {
                flash('survey_error', 'Please answer every required question.');
                redirect('../resident/surveyform.php?survey_id=' . $surveyId);
            }
            $answers[(int) $question['id']] = in_array($value, ['yes', 'no'], true) ? $value : null;
            break;

        case 'rating':
            $value = (int) $raw;
            if ($isRequired && ($value < 1 || $value > 5)) {
                flash('survey_error', 'Please answer every required question.');
                redirect('../resident/surveyform.php?survey_id=' . $surveyId);
            }
            $answers[(int) $question['id']] = ($value >= 1 && $value <= 5) ? (string) $value : null;
            break;

        case 'short_answer':
        default:
            $value = trim((string) $raw);
            if ($isRequired && $value === '') {
                flash('survey_error', 'Please answer every required question.');
                redirect('../resident/surveyform.php?survey_id=' . $surveyId);
            }
            $answers[(int) $question['id']] = $value !== '' ? $value : null;
            break;
    }
}

$db = database();
try {
    $db->beginTransaction();
   
    $submission = $db->prepare('INSERT INTO survey_submissions (survey_id, resident_id, submitted_at) VALUES (?, ?, NOW())');
    $submission->execute([$survey['id'], $resident['id']]);
    $submissionId = (int) $db->lastInsertId();


    $insertAnswer = $db->prepare('INSERT INTO survey_answers (submission_id, question_id, answer_text) VALUES (?, ?, ?)');
    foreach ($answers as $questionId => $answerText) {
        $insertAnswer->execute([$submissionId, $questionId, $answerText]);
    }


    $actualSubmission = $db->prepare('SELECT submitted_at FROM survey_submissions WHERE id = ?');
    $actualSubmission->execute([$submissionId]);
    $actualSubmittedAt = $actualSubmission->fetchColumn();


    $db->commit();
    log_activity($db, 'Submitted', 'Survey', $survey['title']);

    $_SESSION['submission_confirmation'] = [
        'survey_id' => (int) $survey['id'],
        'submitted_at' => $actualSubmittedAt, // Use DB time, not PHP time
    ];
} catch (PDOException $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // Existing error handling
    flash('survey_error', 'You have already submitted this survey.');
    redirect('../resident/surveys.php');
}


redirect('../resident/confirmation.php');
