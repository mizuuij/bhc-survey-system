<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$isEditorPage = defined('RENDER_SURVEY_EDITOR');

$db = database();
// Keep is_active truthful: a survey past its own closing date is closed,
// whether or not an admin ever clicked Deactivate.
$db->exec("UPDATE surveys SET is_active = 0 WHERE is_active = 1 AND closes_at <= CURDATE()");

$surveyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$surveyId) {
    redirect('surveys.php');
}

$statement = $db->prepare('SELECT id, title, description, opens_at, closes_at, is_active FROM surveys WHERE id = ? LIMIT 1');
$statement->execute([$surveyId]);
$survey = $statement->fetch();
if (!$survey) {
    flash('survey_error', 'Survey not found.');
    redirect('surveys.php');
}

$questionsStatement = $db->prepare('SELECT id, question_text, question_type, choices_text, is_required FROM survey_questions WHERE survey_id = ? ORDER BY id ASC');
$questionsStatement->execute([$surveyId]);
$questions = $questionsStatement->fetchAll();
$success = flash('manage_survey_success');
$error = flash('manage_survey_error');
$typeLabels = ['multiple_choice' => 'Multiple Choice', 'yes_no' => 'Yes / No', 'rating' => 'Rating Scale', 'short_answer' => 'Short Answer'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Survey</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
    <link rel="stylesheet" href="../css/manage-survey.css?v=1">
    <link rel="stylesheet" href="../css/confirm-modal.css?v=3">
</head>
<body>
    <div class="portal-shell">
        <div class="sidebar-backdrop"></div>
        <aside class="sidebar">
            <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
            <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
            <nav class="nav-group"><span class="nav-label">Management</span><a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a><a class="nav-link" href="surveys.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a><a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a><a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a><div class="nav-group-item">
            <button type="button" class="nav-link nav-parent" aria-expanded="false"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                <span>Reports</span>
                <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-submenu">
                <a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
                <a class="nav-link nav-sublink" href="member_reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a>
            </div>
        </div><?php if ($admin['role'] === 'admin'): ?><a class="nav-link" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a><?php endif; ?></nav>
            <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
        </aside>

        <main class="main manage-main">
            <button class="menu-toggle" aria-label="Toggle menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <a class="back-link" href="<?= $isEditorPage ? 'manage_survey.php?id=' . (int) $survey['id'] : 'surveys.php' ?>">&larr; <?= $isEditorPage ? 'Back to Survey Details' : 'Back to Survey Management' ?></a>

            <header class="manage-header">
                <div><p class="eyebrow">SURVEY DETAILS</p><h1><?= h($survey['title']) ?></h1><p><?= h($survey['description']) ?></p></div>
                <div class="header-actions">
                    <a class="button button-outline" href="respondents.php?survey_id=<?= (int) $survey['id'] ?>">View Respondents</a>
                    <?php if ($survey['is_active'] && !$isEditorPage): ?>
                        <a class="button button-outline" href="edit_survey.php?id=<?= (int) $survey['id'] ?>">Edit Survey</a>
                        <form action="../../process/update_survey.php" method="post" data-confirm-modal='{"title":"Deactivate this survey?","description":"Residents will no longer be able to answer it until you reactivate it.","confirmLabel":"Yes, deactivate","variant":"danger"}'><input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>"><input type="hidden" name="action" value="deactivate"><button class="button button-danger" type="submit">Deactivate</button></form>
                    <?php elseif ($survey['is_active']): ?>
                        <a class="button button-outline" href="manage_survey.php?id=<?= (int) $survey['id'] ?>">Back to Survey Details</a>
                    <?php elseif (strtotime($survey['closes_at']) <= strtotime('today')): ?>
                        <span class="status status-closed">Deactivated · Period Ended</span>
                    <?php else: ?>
                        <span class="status status-closed">Deactivated</span>
                        <form action="../../process/update_survey.php" method="post" data-confirm-modal='{"title":"Reactivate this survey?","description":"Residents will be able to answer it again right away using its current response period.","confirmLabel":"Yes, reactivate","variant":"info"}'><input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>"><input type="hidden" name="action" value="reactivate"><button class="button button-primary" type="submit">Reactivate</button></form>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($success): ?><p class="notice notice-success" role="status"><?= h($success) ?></p><?php endif; ?>
            <?php if ($error): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>

            <?php if (!$isEditorPage): ?>
            <section class="survey-summary">
                <div><span>Opening date</span><strong><?= h(date('F j, Y', strtotime($survey['opens_at']))) ?></strong></div>
                <div><span>Closing date</span><strong><?= h(date('F j, Y', strtotime($survey['closes_at']))) ?></strong></div>
                <div><span>Status</span><strong class="status <?= $survey['is_active'] ? 'status-active' : 'status-closed' ?>"><?= $survey['is_active'] ? 'Active' : 'Closed' ?></strong></div>
                <div><span>Questions</span><strong><?= count($questions) ?></strong></div>
            </section>

            <?php if (!$survey['is_active'] && strtotime($survey['closes_at']) <= strtotime('today')): ?>
            <section class="reactivate-panel">
                <div class="section-title"><div><h2>Reactivate Survey</h2><p>This survey's response period already ended on <?= h(date('F j, Y', strtotime($survey['closes_at']))) ?>. Set a new opening and closing date to reopen it to residents.</p></div></div>
                <form action="../../process/update_survey.php" method="post" class="reactivate-form" data-confirm-modal='{"title":"Reactivate this survey?","description":"This will reopen the survey to residents using the new opening and closing dates you set below.","confirmLabel":"Yes, reactivate","variant":"info"}'>
                    <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">
                    <input type="hidden" name="action" value="reactivate">
                    <div class="form-grid">
                        <label>New opening date<input type="date" name="opens_at" required min="<?= h(date('Y-m-d')) ?>"></label>
                        <label>New closing date<input type="date" name="closes_at" required min="<?= h(date('Y-m-d')) ?>"></label>
                    </div>
                    <div class="editor-actions"><button class="button button-primary" type="submit">Reactivate Survey</button></div>
                </form>
            </section>
            <?php endif; ?>

            <section class="question-preview"><div class="section-title"><div><h2>Survey Questions</h2><p><?= $survey['is_active'] ? 'Questions residents will answer while this survey is active.' : 'This deactivated survey is read-only until it is reactivated.' ?></p></div></div>
                <?php if (!$questions): ?><p class="empty">This survey has no questions yet. Use Edit Survey to add questions.</p><?php endif; ?>
                <?php foreach ($questions as $index => $question): ?>
                    <article class="preview-question"><div class="question-number"><?= $index + 1 ?></div><div><h3><?= h($question['question_text']) ?> <?= $question['is_required'] ? '<span class="required">Required</span>' : '' ?></h3><p><?= h($typeLabels[$question['question_type']] ?? $question['question_type']) ?></p>
                        <?php if ($question['question_type'] === 'multiple_choice' && $question['choices_text']): ?><ul class="choice-preview"><?php foreach (preg_split('/\R/', $question['choices_text']) as $choice): if (trim($choice) !== ''): ?><li><?= h(trim($choice)) ?></li><?php endif; endforeach; ?></ul><?php endif; ?>
                    </div></article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <?php if ($survey['is_active'] && $isEditorPage): ?>
            <section class="editor" data-editor>
                <div class="section-title"><div><h2>Edit Survey</h2><p>Update the survey details and questions, then save your changes.</p></div><a class="button button-outline" href="manage_survey.php?id=<?= (int) $survey['id'] ?>">Cancel</a></div>
                <form action="../../process/update_survey.php" method="post" data-survey-form>
                    <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>"><input type="hidden" name="action" value="save">
                    <div class="form-grid"><label>Survey title<input type="text" name="title" maxlength="180" required value="<?= h($survey['title']) ?>"></label><label class="full-field">Description<textarea name="description" rows="3"><?= h($survey['description']) ?></textarea></label><label>Opening date<input type="date" name="opens_at" required value="<?= h($survey['opens_at']) ?>" min="<?= h(min(date('Y-m-d'), $survey['opens_at'])) ?>"></label><label>Closing date<input type="date" name="closes_at" required value="<?= h($survey['closes_at']) ?>"></label><label class="full-field">Status<select name="status"><option value="1" <?= $survey['is_active'] ? 'selected' : '' ?>>Active</option><option value="0" <?= !$survey['is_active'] ? 'selected' : '' ?>>Closed</option></select></label></div>
                    <div class="editor-questions"><div class="section-title"><div><h2>Questions</h2><p>At least one question is required.</p></div><button class="button button-outline" type="button" data-add-question>+ Add Question</button></div><div data-question-list>
                        <?php foreach ($questions as $index => $question): ?>
                            <article class="edit-question"><div class="question-editor-heading"><h3>Question <span data-question-number><?= $index + 1 ?></span></h3><button type="button" class="remove-question" data-remove-question>Remove</button></div><label>Question<input type="text" name="question_text[<?= $index ?>]" required value="<?= h($question['question_text']) ?>"></label><div class="question-options"><label>Answer type<select name="question_type[<?= $index ?>]" data-question-type><?php foreach ($typeLabels as $value => $label): ?><option value="<?= h($value) ?>" <?= $question['question_type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><label class="required-control"><input type="checkbox" name="question_required[<?= $index ?>]" value="1" <?= $question['is_required'] ? 'checked' : '' ?>> Required question</label></div><div class="choices-input" data-choices-field <?= $question['question_type'] === 'multiple_choice' ? '' : 'hidden' ?>>
                    <label>Choices</label>
                    <div class="choice-options" data-choice-options>
                        <?php
                            $existingChoices = $question['question_type'] === 'multiple_choice'
                                ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['choices_text']))))
                                : [];
                            if (!$existingChoices) $existingChoices = [''];
                        ?>
                        <?php foreach ($existingChoices as $choice): ?>
                            <div class="choice-option-row" data-choice-row>
                                <input type="text" class="choice-option-input" data-choice-input value="<?= h($choice) ?>" placeholder="e.g. Excellent">
                                <button type="button" class="remove-choice" data-remove-choice aria-label="Remove option">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="secondary-button add-choice" data-add-choice>+ Add Option</button>
                    <textarea name="question_choices[<?= $index ?>]" data-choices-hidden hidden><?= h($question['choices_text']) ?></textarea>
                </div></article>
                        <?php endforeach; ?>
                    </div></div>
                    <div class="editor-actions"><a class="button button-outline" href="manage_survey.php?id=<?= (int) $survey['id'] ?>">Cancel</a><button class="button button-primary" type="submit">Save Changes</button></div>
                </form>
            </section>
            <?php endif; ?>
        </main>
    </div>

        <template id="question-template">
        <article class="edit-question">
        <div class="question-editor-heading"><h3>Question <span data-question-number></span></h3><button type="button" class="remove-question" data-remove-question>Remove</button></div>
        <label>Question<input type="text" name="question_text[]" required placeholder="Enter your question"></label>
        <div class="question-options">
            <label>Answer type<select name="question_type[]" data-question-type><option value="multiple_choice">Multiple Choice</option><option value="yes_no">Yes / No</option><option value="rating">Rating Scale</option><option value="short_answer">Short Answer</option></select></label>
            <label class="required-control"><input type="checkbox" name="question_required[]" value="1" checked> Required question</label>
        </div>
        <div class="choices-input" data-choices-field>
            <label>Choices</label>
            <div class="choice-options" data-choice-options>
                <div class="choice-option-row" data-choice-row>
                    <input type="text" class="choice-option-input" data-choice-input placeholder="e.g. Excellent">
                    <button type="button" class="remove-choice" data-remove-choice aria-label="Remove option">&times;</button>
                </div>
            </div>
            <button type="button" class="secondary-button add-choice" data-add-choice>+ Add Option</button>
            <textarea name="question_choices[]" data-choices-hidden hidden></textarea>
        </div>
        </article>
        </template>
    <script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script><script src="../js/manage-survey.js?v=2"></script><script src="../js/confirm-modal.js?v=3"></script>
</body>
</html>