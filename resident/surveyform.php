<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
$resident = require_login(); $initials = ''; foreach (array_slice(preg_split('/\s+/', trim($resident['head_name'])), 0, 2) as $w) { $initials .= mb_strtoupper(mb_substr($w, 0, 1)); }
$surveyError = flash('survey_error');

$surveyId = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT) ?: 0;

$surveyStatement = database()->prepare('SELECT id, title, description, opens_at, closes_at FROM surveys WHERE id = ? AND is_active = 1 AND CURDATE() >= opens_at AND CURDATE() < closes_at LIMIT 1');
$surveyStatement->execute([$surveyId]);
$survey = $surveyStatement->fetch();
if (!$survey) {
    flash('survey_error', 'That survey is not available right now.');
    redirect('Surveys.php');
}

$alreadyStatement = database()->prepare('SELECT id FROM survey_submissions WHERE survey_id = ? AND resident_id = ? LIMIT 1');
$alreadyStatement->execute([$survey['id'], $resident['id']]);
if ($alreadyStatement->fetch()) {
    flash('survey_error', 'Your household has already submitted this survey.');
    redirect('Surveys.php');
}

$questionsStatement = database()->prepare('SELECT id, question_text, question_type, choices_text, is_required FROM survey_questions WHERE survey_id = ? ORDER BY id ASC');
$questionsStatement->execute([$survey['id']]);
$questions = $questionsStatement->fetchAll();
if (!$questions) {
    flash('survey_error', 'This survey has no questions yet. Please check back later.');
    redirect('Surveys.php');
}
$questionCount = count($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($survey['title']) ?> — Barangay Health Center Resident Profiling & Survey Management System</title>
<link rel="stylesheet" href="../assets/css/surveyform.css?v=6">
<link rel="stylesheet" href="../assets/css/logout.css">
</head>
<body>

<div class="portal-shell">

    <div class="sidebar-backdrop"></div>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-seal"><img src="../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div>
            <div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System
                <small>Resident Portal</small>
            </div>
        </div>

        <div class="id-card">
            <div class="id-eyebrow">Household ID</div>
            <div class="id-card-row">
                <div class="id-avatar"><?= h($initials) ?></div>
                <div class="id-card-name"><?= h($resident['head_name']) ?>
                    <small>Household Head</small>
                </div>
            </div>
            <div class="id-card-perf"></div>
            <div class="id-card-number"><span>Household No.</span><?= h($resident['household_number']) ?></div>
        </div>

        <nav class="nav-group">
            <span class="nav-label">Menu</span>
            <a class="nav-link" href="dashboard.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>
                Dashboard
            </a>
            <a class="nav-link" href="surveys.php" aria-current="page">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                Available Surveys
            </a>
            <a class="nav-link" href="profile.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                Profile
            </a>
            <a class="nav-link" href="changepassword.php">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.85 12.15 19 4"/><path d="M18 5l2 2"/><path d="M15 8l2 2"/></svg></span>
                Change Password
            </a>
        </nav>

        <div class="nav-footer">
            <a class="nav-link" href="../process/logout.php" onclick="event.preventDefault(); logout();">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                Log Out
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <button class="menu-toggle" aria-label="Toggle menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="page-eyebrow">Open <?= h(date('M j', strtotime($survey['opens_at']))) ?> – <?= h(date('M j, Y', strtotime($survey['closes_at']))) ?></div>
                <h1 class="page-title"><?= h($survey['title']) ?></h1>
                <p class="page-sub">Please answer honestly. Your household can only submit this survey once.</p>
            </div>
        </div>

        <div class="card">
            <div class="progress-label">
                <span><?= $questionCount ?> question<?= $questionCount === 1 ? '' : 's' ?></span>
                <span>Takes about 2 minutes</span>
            </div>
            <div class="progress-track"><div class="progress-fill" style="width: 100%;"></div></div>

            <?php if ($surveyError): ?><p class="form-error" role="alert"><?= h($surveyError) ?></p><?php endif; ?>
            <form id="surveyForm" method="post" action="../process/submit_survey.php">
                <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">

                <?php foreach ($questions as $index => $question): ?>
                    <?php
                        $num = $index + 1;
                        $fieldName = 'question_' . (int) $question['id'];
                        $isRequired = (bool) $question['is_required'];
                        $choices = $question['question_type'] === 'multiple_choice'
                            ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['choices_text']))))
                            : [];
                    ?>
                    <div class="question-block">
                        <div class="question-text">
                            <span class="question-num"><?= $num ?></span>
                            <?= h($question['question_text']) ?>
                            <?php if (!$isRequired): ?><span class="question-optional">(optional)</span><?php endif; ?>
                        </div>

                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                            <div class="option-list">
                                <?php foreach ($choices as $choice): ?>
                                    <label class="option-item"><input type="radio" name="<?= h($fieldName) ?>" value="<?= h($choice) ?>" <?= $isRequired ? 'required' : '' ?>> <?= h($choice) ?></label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($question['question_type'] === 'yes_no'): ?>
                            <div class="yn-row">
                                <label class="option-item"><input type="radio" name="<?= h($fieldName) ?>" value="yes" <?= $isRequired ? 'required' : '' ?>> Yes</label>
                                <label class="option-item"><input type="radio" name="<?= h($fieldName) ?>" value="no"> No</label>
                            </div>

                        <?php elseif ($question['question_type'] === 'rating'): ?>
								<input type="hidden" name="<?= h($fieldName) ?>" data-rating-input <?= $isRequired ? 'data-required' : '' ?>>			                        		
                        		<div class="rating-row" data-value="0" data-rating-row>
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                    <button type="button" class="rating-star" aria-label="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26"/></svg></button>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-caption">Tap a star to rate</div>

                        <?php else: ?>
                            <div style="margin-left: 32px;">
                                <textarea name="<?= h($fieldName) ?>" placeholder="Type your answer here..." <?= $isRequired ? 'required' : '' ?>></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary btn-full">Submit Survey</button>
            </form>
        </div>
    </main>
</div>

<script src="../assets/js/surveyform.js?v=2"></script>
<script src="../assets/js/logout.js"></script>
</body>
</html>