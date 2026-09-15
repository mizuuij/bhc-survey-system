<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();

$db = database();
// A survey's closing date is the real source of truth for whether it's still
// open — auto-close anything whose window has lapsed so "Active" never lags
// behind reality just because nobody clicked Deactivate.
$db->exec("UPDATE surveys SET is_active = 0 WHERE is_active = 1 AND closes_at <= CURDATE()");

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'active', 'closed'], true)) {
    $status = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$where = ['archived_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== 'all') {
    $where[] = 'is_active = ?';
    $params[] = $status === 'active' ? 1 : 0;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);
$countStatement = $db->prepare('SELECT COUNT(*) FROM surveys' . $whereSql);
$countStatement->execute($params);
$surveyCount = (int) $countStatement->fetchColumn();
$pageCount = max(1, (int) ceil($surveyCount / $perPage));
$page = min($page, $pageCount);
$offset = ($page - 1) * $perPage;
$surveyStatement = $db->prepare('SELECT id, title, description, opens_at, closes_at, is_active FROM surveys' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$surveyStatement->execute($params);
$surveys = $surveyStatement->fetchAll();
$pageUrl = static function (int $targetPage) use ($search, $status): string {
    $query = ['page' => $targetPage];
    if ($search !== '') $query['q'] = $search;
    if ($status !== 'all') $query['status'] = $status;
    return 'surveys.php?' . http_build_query($query);
};
$success = flash('survey_success');
$error = flash('survey_error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Management</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
    <link rel="stylesheet" href="../../assets/css/admin-management.css">
    <link rel="stylesheet" href="../css/surveys.css?v=7">
</head>
<body>
    <div class="portal-shell">
        <div class="sidebar-backdrop"></div>
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div>
                <div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div>
            </div>
            <div class="id-card" data-navigate-href="admin_profile.php">
                <div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div>
                <div class="id-card-row">
                    <div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div>
                    <div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div>
                </div>
                <div class="id-card-perf"></div>
                <div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div>
            </div>
            <nav class="nav-group">
                <span class="nav-label">Management</span>
                <a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
                <a class="nav-link" href="surveys.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
                <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
                <a class="nav-link" href="results.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a>
                <div class="nav-group-item">
            <button type="button" class="nav-link nav-parent" aria-expanded="false"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                <span>Reports</span>
                <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-submenu">
                <a class="nav-link nav-sublink" href="reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Reports</a>
                <a class="nav-link nav-sublink" href="member_reports.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Reports</a>
            </div>
        </div>
                <?php if ($admin['role'] === 'admin'): ?><a class="nav-link" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M16 11a4 4 0 0 1 0-8"/><path d="M21 21v-2a6 6 0 0 0-4-5.7"/></svg></span>User Management</a><?php endif; ?>
            </nav>
            <div class="nav-footer"><a class="nav-link" href="../../process/admin_logout.php" onclick="event.preventDefault(); logout();"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Log Out</a></div>
        </aside>

        <main class="main">
            <div class="sticky-head">
            <div class="topbar">
                <div>
                    <button class="menu-toggle" aria-label="Toggle menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div class="page-eyebrow">Survey administration</div>
                    <h1 class="page-title">Survey Management</h1>
                    <p class="page-sub">Create, review, update, deactivate, or reactivate resident surveys.</p>
                </div>
                <button class="btn btn-primary" type="button" data-open-survey-dialog>+ New Survey</button>
            </div>
        <?php if ($success): ?>
            <p class="notice notice-success" role="status"><?= h($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="notice notice-error" role="alert"><?= h($error) ?></p>
        <?php endif; ?>

        <form class="survey-tools" method="get" role="search" data-live-filter>
            <label class="tool-search" for="survey-search">Search surveys
                <input id="survey-search" type="search" name="q" value="<?= h($search) ?>" placeholder="Search by title or description" autocomplete="off" data-live-search>
            </label>
            <label class="tool-filter" for="survey-status">Status
                <select id="survey-status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </label>
            <?php if ($search !== '' || $status !== 'all'): ?><a class="btn btn-outline clear-filters" href="surveys.php">Back to Survey List</a><?php endif; ?>
        </form>
        </div>
        <div class="survey-page">
        <div data-live-results aria-live="polite">
        <p class="list-summary"><?= $surveyCount ?> survey<?= $surveyCount === 1 ? '' : 's' ?> found<?= $surveyCount > $perPage ? ' · Page ' . $page . ' of ' . $pageCount : '' ?>.</p>

        <section class="survey-list" aria-label="Surveys">
            <?php if (!$surveys): ?>
                <div class="empty">
                    <?php if ($search !== '' || $status !== 'all'): ?>
                        No surveys match your search or selected status. <a href="surveys.php">Back to Survey List</a>
                    <?php else: ?>
                        No surveys have been created yet. Select <strong>+ New Survey</strong> to create one.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($surveys as $survey): ?>
                <article class="survey-card">
                    <div class="survey-info">
                        <h2><?= h($survey['title']) ?></h2>
                        <p><?= h($survey['description']) ?></p>
                        <div class="survey-meta">
                            <span class="dot <?= $survey['is_active'] ? 'active' : 'closed' ?>"></span>
                            <strong><?= $survey['is_active'] ? 'Active' : 'Closed' ?></strong>
                            <span><?= h(date('F j, Y', strtotime($survey['opens_at']))) ?> - <?= h(date('F j, Y', strtotime($survey['closes_at']))) ?></span>
                        </div>
                    </div>
                    <div class="survey-card-actions">
                        <a class="btn btn-outline btn-sm icon-action" href="manage_survey.php?id=<?= (int) $survey['id'] ?>" aria-label="Manage survey" data-tooltip="Manage Survey"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h3.5L18.4 9.1l-3.5-3.5L4 16.5Z"/><path d="m13.9 6.1 3.5 3.5"/></svg></a>
                        <?php
                            $archiveSurveyConfirm = json_encode([
                                'title' => 'Archive this survey?',
                                'description' => '"' . $survey['title'] . '" will be closed and moved to the Archive, and removed from Survey Reports. You can restore it from there at any time.',
                                'confirmLabel' => 'Yes, archive',
                                'variant' => 'danger',
                            ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <form action="../../process/admin_archive_survey.php" method="post" data-confirm-modal='<?=$archiveSurveyConfirm?>'>
                            <input type="hidden" name="survey_id" value="<?= (int) $survey['id'] ?>">
                            <button class="btn btn-danger btn-sm icon-action" type="submit" aria-label="Archive survey" data-tooltip="Archive"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v4H3z"/><path d="M5 8v11h14V8"/><path d="M10 12h4"/></svg></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <?php if ($surveyCount > 0): ?>
            <nav class="pagination" aria-label="Survey pages">
                <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= h($pageUrl($page - 1)) ?>">Previous</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Previous</span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?><a class="btn btn-outline" href="<?= h($pageUrl($page + 1)) ?>">Next</a><?php else: ?><span class="btn btn-outline" aria-disabled="true">Next</span><?php endif; ?>
            </nav>
        <?php endif; ?>
        </div>
            </div>
        </main>
    </div>

    <dialog class="survey-dialog" data-survey-dialog aria-labelledby="new-survey-title">
        <form class="survey-form" action="../../process/create_survey.php" method="post">
            <div class="dialog-heading">
                <div>
                    <p class="dialog-eyebrow">SURVEY SETUP</p>
                    <h2 id="new-survey-title">Create New Survey</h2>
                </div>
                <button class="close-dialog" type="button" aria-label="Close" data-close-survey-dialog>&times;</button>
            </div>

            <div class="form-grid">
                <label class="field field-full">Survey title
                    <input type="text" name="title" maxlength="180" required placeholder="e.g. Household Health Survey">
                </label>
                <label class="field field-full">Description
                    <textarea name="description" rows="3" placeholder="Briefly describe this survey."></textarea>
                </label>
                <label class="field">Opening date
                    <input type="date" name="opens_at" required min="<?= h(date('Y-m-d')) ?>">
                </label>
                <label class="field">Closing date
                    <input type="date" name="closes_at" required min="<?= h(date('Y-m-d')) ?>">
                </label>
                <label class="field field-full">Status
                    <select name="status">
                        <option value="1">Active</option>
                        <option value="0">Closed</option>
                    </select>
                </label>
            </div>

            <section class="questions-section" aria-labelledby="questions-heading">
                <div class="questions-heading">
                    <div>
                        <h3 id="questions-heading">Survey Questions</h3>
                        <p>Add at least one question. Use "Add Option" to add multiple-choice choices.</p>
                    </div>
                    <button class="btn btn-outline" type="button" data-add-question>+ Add Question</button>
                </div>
                <div class="question-list" data-question-list></div>
            </section>

            <div class="form-actions">
                <button class="btn btn-outline" type="button" data-close-survey-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit">Create Survey</button>
            </div>
        </form>
    </dialog>

    <template id="question-template">
        <article class="question-card">
            <div class="question-card-heading">
                <h4>Question <span data-question-number></span></h4>
                <button class="remove-question" type="button" data-remove-question>Remove</button>
            </div>
            <label class="field">Question
                <input type="text" name="question_text[]" required placeholder="Enter your question">
            </label>
            <div class="question-options">
                <label class="field">Answer type
                    <select name="question_type[]" data-question-type>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="yes_no">Yes / No</option>
                        <option value="rating">Rating Scale</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </label>
                <label class="required-choice"><input type="checkbox" name="question_required[]" value="1" checked> Required question</label>
            </div>
            <div class="field choices-field" data-choices-field>
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

    <script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
    <script src="../js/surveys.js?v=5"></script>
    <script src="../js/live-filter.js?v=1"></script>
</body>
</html>