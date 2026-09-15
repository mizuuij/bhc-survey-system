<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/admin_auth.php';

$admin = require_admin();
$db = database();
$isPreview = defined('RENDER_RESULTS_PREVIEW');

$selectedSurveyId = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT) ?: 0;
if ($selectedSurveyId > 0 && !$isPreview) {
    redirect('view_results.php?survey_id=' . $selectedSurveyId);
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'active', 'closed'], true)) {
    $status = 'all';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = 's.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($status === 'active') {
    $where[] = "s.is_active = 1 AND CURDATE() >= s.opens_at AND CURDATE() < s.closes_at";
} elseif ($status === 'closed') {
    $where[] = "NOT (s.is_active = 1 AND CURDATE() >= s.opens_at AND CURDATE() < s.closes_at)";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$perPage = 10;
$countStatement = $db->prepare('SELECT COUNT(*) FROM surveys s' . $whereSql);
$countStatement->execute($params);
$surveyCount = (int) $countStatement->fetchColumn();
$pageCount = max(1, (int) ceil($surveyCount / $perPage));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pageCount);
$offset = ($page - 1) * $perPage;
$paginationSql = $isPreview ? '' : ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

$surveyStatement = $db->prepare(
    "SELECT s.id, s.title, s.description, s.opens_at, s.closes_at, s.is_active,
            COUNT(DISTINCT sub.id) AS submission_count,
            MAX(sub.submitted_at) AS last_submission
     FROM surveys s
     LEFT JOIN survey_submissions sub ON sub.survey_id = s.id
     $whereSql
     GROUP BY s.id, s.title, s.description, s.opens_at, s.closes_at, s.is_active
     ORDER BY s.id DESC" . $paginationSql
);
$surveyStatement->execute($params);
$surveys = $surveyStatement->fetchAll();

$pageUrl = static function (int $targetPage) use ($search, $status): string {
    $query = ['page' => $targetPage];
    if ($search !== '') $query['q'] = $search;
    if ($status !== 'all') $query['status'] = $status;
    return 'results.php?' . http_build_query($query);
};

$isSurveyOpen = static function (array $survey): bool {
    $today = date('Y-m-d');
    return (int) $survey['is_active'] === 1 && $today >= $survey['opens_at'] && $today < $survey['closes_at'];
};

$selectedSurvey = null;
if ($isPreview && $selectedSurveyId > 0) {
    foreach ($surveys as $survey) {
        if ((int) $survey['id'] === $selectedSurveyId) {
            $selectedSurvey = $survey;
            break;
        }
    }
}
if ($isPreview && $selectedSurvey === null) {
    redirect('results.php');
}

$submissionCount = 0;
$lastSubmissionAt = null;
$questions = [];
$chartPayload = [];
$typeLabels = [
    'multiple_choice' => 'Multiple Choice',
    'yes_no' => 'Yes / No',
    'rating' => 'Rating Scale',
    'short_answer' => 'Written Answer',
];

if ($selectedSurvey !== null) {
    $submissionCount = (int) $selectedSurvey['submission_count'];
    $lastSubmissionAt = $selectedSurvey['last_submission'] ?: null;

    $questionStatement = $db->prepare(
        "SELECT q.id, q.question_text, q.question_type, q.choices_text,
                (SELECT COUNT(*) FROM survey_answers a WHERE a.question_id = q.id AND a.answer_text IS NOT NULL AND a.answer_text <> '') AS answer_count
         FROM survey_questions q
         WHERE q.survey_id = ?
         ORDER BY q.id ASC"
    );
    $questionStatement->execute([$selectedSurveyId]);
    $questions = $questionStatement->fetchAll();

    foreach ($questions as &$question) {
        $question['average'] = null;
        $question['breakdown'] = [];
        $question['written_answers'] = [];

        if (in_array($question['question_type'], ['multiple_choice', 'yes_no'], true)) {
            $options = $question['question_type'] === 'yes_no'
                ? ['yes', 'no']
                : array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['choices_text']))));
            $counts = array_fill_keys($options, 0);
            $answerStatement = $db->prepare(
                "SELECT answer_text, COUNT(*) AS total
                 FROM survey_answers
                 WHERE question_id = ? AND answer_text IS NOT NULL AND answer_text <> ''
                 GROUP BY answer_text"
            );
            $answerStatement->execute([$question['id']]);
            foreach ($answerStatement->fetchAll() as $answer) {
                $counts[$answer['answer_text']] = (int) $answer['total'];
            }
            foreach ($counts as $answerText => $count) {
                $question['breakdown'][] = ['label' => $answerText, 'count' => $count];
            }
        } elseif ($question['question_type'] === 'rating') {
            $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            $ratingStatement = $db->prepare(
                "SELECT CAST(answer_text AS UNSIGNED) AS rating, COUNT(*) AS total
                 FROM survey_answers
                 WHERE question_id = ? AND answer_text IS NOT NULL
                 GROUP BY rating"
            );
            $ratingStatement->execute([$question['id']]);
            foreach ($ratingStatement->fetchAll() as $rating) {
                $value = (int) $rating['rating'];
                if (isset($counts[$value])) $counts[$value] = (int) $rating['total'];
            }
            $averageStatement = $db->prepare('SELECT ROUND(AVG(CAST(answer_text AS DECIMAL(3,1))), 1) FROM survey_answers WHERE question_id = ? AND answer_text IS NOT NULL');
            $averageStatement->execute([$question['id']]);
            $question['average'] = $averageStatement->fetchColumn() ?: null;
            foreach ($counts as $rating => $count) {
                $question['breakdown'][] = ['label' => $rating . ' star' . ($rating === 1 ? '' : 's'), 'count' => $count];
            }
        } else {
            $writtenStatement = $db->prepare(
                "SELECT a.answer_text, sub.submitted_at
                 FROM survey_answers a
                 JOIN survey_submissions sub ON sub.id = a.submission_id
                 WHERE a.question_id = ? AND a.answer_text IS NOT NULL AND a.answer_text <> ''
                 ORDER BY sub.submitted_at DESC, a.id DESC"
            );
            $writtenStatement->execute([$question['id']]);
            $question['written_answers'] = $writtenStatement->fetchAll();
        }
    }
    unset($question);

    foreach ($questions as $question) {
        if (!in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)) {
            continue;
        }
        $chartPayload[] = [
            'id' => (int) $question['id'],
            'type' => $question['question_type'],
            'labels' => array_map(fn($item) => $item['label'], $question['breakdown']),
            'data' => array_map(fn($item) => (int) $item['count'], $question['breakdown']),
        ];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $isPreview ? h($selectedSurvey['title'] ?? 'Results') . ' — Results' : 'Results Dashboard' ?></title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=13">
<link rel="stylesheet" href="../css/logout.css">
<link rel="stylesheet" href="../../assets/css/admin-management.css">
<link rel="stylesheet" href="../css/surveys.css?v=5">
<link rel="stylesheet" href="../css/results.css?v=2">
<?php if ($isPreview): ?><script src="../../assets/js/chart.umd.min.js"></script><?php endif; ?>
</head>
<body>
<div class="portal-shell">
<div class="sidebar-backdrop"></div>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-seal"><img src="../../assets/img/barangay-longos-logo.jpg" alt="Barangay Longos Logo"></div><div class="wordmark">Barangay Longos<br>Profiling &amp; Survey System<small>Administrator Portal</small></div></div>
    <div class="id-card" data-navigate-href="admin_profile.php"><div class="id-eyebrow"><?= $admin['role'] === 'admin' ? 'Administrator Account' : 'Staff Account' ?></div><div class="id-card-row"><div class="id-avatar"><?= h(strtoupper(substr($admin['full_name'], 0, 2))) ?></div><div class="id-card-name"><?= h($admin['full_name']) ?><small><?= h(ucfirst($admin['role'])) ?></small></div></div><div class="id-card-perf"></div><div class="id-card-number"><span>Username</span><?= h($admin['username']) ?></div></div>
    <nav class="nav-group"><span class="nav-label">Management</span>
        <a class="nav-link" href="dashboard.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span>Dashboard</a>
        <a class="nav-link" href="surveys.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>Survey Management</a>
        <a class="nav-link" href="members.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Member Management</a><a class="nav-link" href="archive.php"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><line x1="10" y1="13" x2="14" y2="13"/></svg></span>Archive</a>
        <a class="nav-link" href="results.php" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="13"/><line x1="22" y1="20" x2="22" y2="7"/></svg></span>Results Dashboard</a>
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
            <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            <div class="page-eyebrow"><?= $isPreview ? 'Survey statistics' : 'Response statistics' ?></div>
            <h1 class="page-title"><?= $isPreview ? h($selectedSurvey['title'] ?? 'Results') : 'Results Dashboard' ?></h1>
            <p class="page-sub"><?= $isPreview ? 'Response statistics and question results for this survey.' : 'Search or filter surveys, then select one to view its response statistics.' ?></p>
        </div>
        <?php if ($isPreview): ?>
        <div class="results-toolbar">
            <a class="btn btn-light" href="results.php">Back to Results List</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!$isPreview): ?>
        <form class="survey-tools" method="get" role="search" data-live-filter>
            <label class="tool-search" for="results-search">Search surveys
                <input id="results-search" type="search" name="q" value="<?= h($search) ?>" placeholder="Search by survey title" autocomplete="off" data-live-search>
            </label>
            <label class="tool-filter" for="results-status">Status
                <select id="results-status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </label>
            <?php if ($search !== '' || $status !== 'all'): ?><a class="btn btn-outline clear-filters" href="results.php">Back to Survey List</a><?php endif; ?>
        </form>
    <?php endif; ?>
    </div>

    <?php if (!$isPreview): ?>
        <div class="survey-page">
        <div data-live-results aria-live="polite">
        <p class="list-summary"><?= $surveyCount ?> survey<?= $surveyCount === 1 ? '' : 's' ?> found<?= $surveyCount > $perPage ? ' · Page ' . $page . ' of ' . $pageCount : '' ?>.</p>

        <section class="survey-list" aria-label="Surveys">
            <?php if (!$surveys): ?>
                <div class="empty">
                    <?php if ($search !== '' || $status !== 'all'): ?>
                        No surveys match your search or selected status. <a href="results.php">Back to Survey List</a>
                    <?php else: ?>
                        No surveys have been created yet.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($surveys as $survey): ?>
                <?php $surveyIsOpen = $isSurveyOpen($survey); ?>
                <article class="survey-card results-survey-card">
                    <div class="survey-info">
                        <h2><?= h($survey['title']) ?></h2>
                        <p><?= h($survey['description']) ?></p>
                        <div class="survey-meta">
                            <span class="dot <?= $surveyIsOpen ? 'active' : 'closed' ?>"></span>
                            <strong><?= $surveyIsOpen ? 'Active' : 'Closed' ?></strong>
                            <span><?= h(date('F j, Y', strtotime($survey['opens_at']))) ?> - <?= h(date('F j, Y', strtotime($survey['closes_at']))) ?></span>
                            <span><?= (int) $survey['submission_count'] ?> submission<?= (int) $survey['submission_count'] === 1 ? '' : 's' ?></span>
                            <?php if ($survey['last_submission']): ?><span>Last response <?= h(date('M j, Y', strtotime($survey['last_submission']))) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <a class="btn btn-outline manage-survey" href="results.php?survey_id=<?= (int) $survey['id'] ?>">View Results</a>
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
    <?php else: ?>
        <?php $isOpen = $isSurveyOpen($selectedSurvey); ?>
        <?php
            $closesAtDate = new DateTime($selectedSurvey['closes_at']);
            if ($isOpen) {
                $daysRemaining = (int) (new DateTime(date('Y-m-d')))->diff($closesAtDate)->format('%a');
                $timeRemainingLabel = $daysRemaining === 0 ? 'Closes today' : $daysRemaining . ' day' . ($daysRemaining === 1 ? '' : 's');
                $timeRemainingHint = 'Until survey closes';
            } else {
                $timeRemainingLabel = 'Closed';
                $timeRemainingHint = 'Closed ' . $closesAtDate->format('M j, Y');
            }
        ?>
        <ul class="stat-list">
            <li class="stat-item stat-submissions">
                <span class="stat-item-label">Submissions</span>
                <span class="stat-item-value"><?= $submissionCount ?></span>
            </li>
            <li class="stat-item stat-questions">
                <span class="stat-item-label">Questions</span>
                <span class="stat-item-value"><?= count($questions) ?></span>
            </li>
            <li class="stat-item stat-recent">
                <span class="stat-item-label">Most Recent Submission</span>
                <span class="stat-item-value"><?= $lastSubmissionAt ? h(date('M j, g:i A', strtotime($lastSubmissionAt))) : '-' ?></span>
            </li>
            <li class="stat-item stat-remaining">
                <span class="stat-item-label">Time Remaining</span>
                <span class="stat-item-value"><?= h($timeRemainingLabel) ?></span>
            </li>
        </ul>

        <section class="card results-summary">
            <div class="card-title">Survey Summary</div>
            <table class="responsive-table">
                <thead><tr><th>Survey</th><th>Response Period</th><th>Status</th></tr></thead>
                <tbody><tr>
                    <td data-label="Survey"><?= h($selectedSurvey['title']) ?></td>
                    <td data-label="Response Period"><?= h(date('M j, Y', strtotime($selectedSurvey['opens_at']))) ?> - <?= h(date('M j, Y', strtotime($selectedSurvey['closes_at']))) ?></td>
                    <td data-label="Status"><span class="badge <?= $isOpen ? 'badge-success' : 'badge-danger' ?>"><?= $isOpen ? 'Open' : 'Closed' ?></span></td>
                </tr></tbody>
            </table>
        </section>

        <section class="question-results">
            <div><div class="card-title">Question Results</div><div class="card-desc">Each chart and table below is labelled with the exact question it summarizes.</div></div>
            <?php if (!$questions): ?><div class="question-card"><p class="empty">This survey has no questions.</p></div><?php endif; ?>
            <?php foreach ($questions as $index => $question): ?>
                <article class="question-card">
                    <div class="question-heading">
                        <h3>Question <?= $index + 1 ?>: <?= h($question['question_text']) ?></h3>
                        <span class="badge badge-muted"><?= h($typeLabels[$question['question_type']]) ?></span>
                    </div>
                    <div class="question-meta"><?= (int) $question['answer_count'] ?> answer<?= (int) $question['answer_count'] === 1 ? '' : 's' ?> recorded</div>
                    <?php if (in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)): ?>
                        <?php if ($question['question_type'] === 'rating'): ?><p class="question-meta">Average rating: <?= $question['average'] ?? '-' ?><?= $question['average'] !== null ? ' / 5' : '' ?></p><?php endif; ?>
                        <div class="chart-panel">
                            <div class="chart-title">Response Distribution</div>
                            <div class="chart-canvas-wrap" style="height:280px">
                                <canvas id="chart-q<?= (int) $question['id'] ?>" role="img" aria-label="Response distribution for <?= h($question['question_text']) ?>"></canvas>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if (!$question['written_answers']): ?><p class="empty">No written answers were provided.</p><?php else: ?>
                            <table class="written-table"><thead><tr><th>Submitted</th><th>Response</th></tr></thead><tbody>
                            <?php foreach ($question['written_answers'] as $answer): ?><tr><td><?= h(date('M j, Y g:i A', strtotime($answer['submitted_at']))) ?></td><td><?= nl2br(h($answer['answer_text'])) ?></td></tr><?php endforeach; ?>
                            </tbody></table>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</div>
<script src="../../assets/js/dashboard.js?v=11"></script><script src="../js/logout.js"></script>
<?php if (!$isPreview): ?><script src="../js/live-filter.js?v=1"></script><?php endif; ?>
<?php if ($isPreview): ?>
<script>
(function () {
    var chartData = <?= json_encode($chartPayload, JSON_UNESCAPED_SLASHES) ?>;
    if (!window.Chart || !Array.isArray(chartData)) return;

    chartData.forEach(function (q) {
        var canvas = document.getElementById('chart-q' + q.id);
        if (!canvas) return;

        var barColor = '#526ddc';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: q.labels,
                datasets: [{
                    label: 'Responses',
                    data: q.data,
                    backgroundColor: barColor,
                    borderRadius: 5,
                    maxBarThickness: 56
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0) || 1;
                                var pct = Math.round((ctx.parsed.y / total) * 100);
                                return ctx.parsed.y + ' response' + (ctx.parsed.y === 1 ? '' : 's') + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } } },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 40,
                            font: { size: 11 },
                            callback: function (value) {
                                var label = this.getLabelForValue(value);
                                return label.length > 18 ? label.slice(0, 15) + '...' : label;
                            }
                        }
                    }
                }
            }
        });
    });
})();
</script>
<?php endif; ?>
</body>
</html>