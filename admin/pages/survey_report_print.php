<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';
$admin = require_admin();
$db = database();

$surveyId = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT);
if (!$surveyId) {
    redirect('reports.php');
}

$surveyStatement = $db->prepare(
    "SELECT s.id, s.title, s.opens_at, s.closes_at, s.is_active,
            COUNT(DISTINCT sub.id) AS responses
     FROM surveys s
     LEFT JOIN survey_submissions sub ON sub.survey_id = s.id
     WHERE s.id = ?
     GROUP BY s.id, s.title, s.opens_at, s.closes_at, s.is_active"
);
$surveyStatement->execute([$surveyId]);
$survey = $surveyStatement->fetch();
if (!$survey) {
    flash('survey_error', 'That survey report could not be found.');
    redirect('reports.php');
}

$statement = $db->prepare(
    "SELECT q.id, q.question_text, q.question_type, q.choices_text,
            COUNT(CASE WHEN a.answer_text IS NOT NULL AND a.answer_text <> '' THEN 1 END) AS answer_count
     FROM survey_questions q
     LEFT JOIN survey_answers a ON a.question_id = q.id
     WHERE q.survey_id = ?
     GROUP BY q.id, q.question_text, q.question_type, q.choices_text
     ORDER BY q.id"
);
$statement->execute([$surveyId]);
$questions = $statement->fetchAll();

foreach ($questions as &$question) {
    $question['rows'] = [];
    $question['average'] = null;

    if (in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)) {
        if ($question['question_type'] === 'yes_no') {
            $labels = ['yes', 'no'];
        } elseif ($question['question_type'] === 'rating') {
            $labels = ['1', '2', '3', '4', '5'];
        } else {
            $labels = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['choices_text']))));
        }

        $counts = array_fill_keys($labels, 0);
        $answerStatement = $db->prepare(
            "SELECT answer_text, COUNT(*) AS total
             FROM survey_answers
             WHERE question_id = ? AND answer_text IS NOT NULL AND answer_text <> ''
             GROUP BY answer_text"
        );
        $answerStatement->execute([(int) $question['id']]);
        foreach ($answerStatement->fetchAll() as $answer) {
            $counts[(string) $answer['answer_text']] = (int) $answer['total'];
        }

        $total = array_sum($counts);
        foreach ($counts as $label => $count) {
            $question['rows'][] = [
                'label' => (string) $label,
                'count' => (int) $count,
                'percent' => $total > 0 ? round(((int) $count / $total) * 100, 1) : 0,
            ];
        }

        if ($question['question_type'] === 'rating') {
            $averageStatement = $db->prepare(
                "SELECT ROUND(AVG(CAST(answer_text AS DECIMAL(3,1))), 1)
                 FROM survey_answers
                 WHERE question_id = ? AND answer_text IS NOT NULL AND answer_text <> ''"
            );
            $averageStatement->execute([(int) $question['id']]);
            $question['average'] = $averageStatement->fetchColumn() ?: null;
        }
    } else {
        $writtenStatement = $db->prepare(
            "SELECT a.answer_text, sub.submitted_at
             FROM survey_answers a
             JOIN survey_submissions sub ON sub.id = a.submission_id
             WHERE a.question_id = ? AND a.answer_text IS NOT NULL AND a.answer_text <> ''
             ORDER BY sub.submitted_at DESC, a.id DESC"
        );
        $writtenStatement->execute([(int) $question['id']]);
        $question['rows'] = $writtenStatement->fetchAll();
    }
}
unset($question);

$typeLabels = [
    'multiple_choice' => 'Multiple Choice',
    'yes_no' => 'Yes / No',
    'rating' => 'Rating Scale',
    'short_answer' => 'Written Answer',
];

$isOpen = (int) $survey['is_active'] === 1 && date('Y-m-d') >= $survey['opens_at'] && date('Y-m-d') < $survey['closes_at'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Survey Results Report — <?= h($survey['title']) ?></title>
<link rel="stylesheet" href="../../assets/css/dashboard.css?v=15">
<style>
.print-toolbar{align-items:center;background:#f7f8fe;border-bottom:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;padding:14px 24px}
.report-document{max-width:1040px;margin:22px auto 0;background:#fff;border:1px solid var(--border);box-shadow:0 8px 28px rgba(25,35,70,.08);padding:28px}.document-head{background:#f7f8fe;border:1px solid var(--border);border-top:4px solid #293d9e;border-radius:12px;margin-bottom:22px;padding:22px}.document-kicker{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#526ddc;font-weight:800}.document-title{font-size:1.6rem;line-height:1.25;margin:7px 0;overflow-wrap:anywhere}.document-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.meta-box{background:#fff;border:1px solid var(--border);border-radius:8px;min-width:0;padding:12px}.meta-label{font-size:.68rem;text-transform:uppercase;color:var(--muted);font-weight:700}.meta-value{font-weight:700;line-height:1.4;margin-top:4px;overflow-wrap:anywhere}.report-section{margin-top:24px}.report-section h2{font-size:1.05rem;margin:0;padding:0}.question-block{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 3px 12px rgba(25,35,70,.04);break-inside:avoid;margin-top:14px;padding:18px}.question-block h3{font-size:.98rem;line-height:1.45;margin:0 0 8px;overflow-wrap:anywhere}.question-note{background:#f3f5fa;border-radius:6px;color:var(--muted);display:inline-block;font-size:.78rem;line-height:1.4;margin:0 0 14px;padding:6px 9px}.table-scroll{width:100%;overflow-x:auto}.report-table{table-layout:fixed;width:100%;border-collapse:collapse}.report-table col.col-response{width:56%}.report-table col.col-count{width:20%}.report-table col.col-percent{width:24%}.report-table th,.report-table td{border:1px solid #d9deea;line-height:1.45;overflow-wrap:anywhere;word-break:break-word;padding:9px 11px;text-align:left;vertical-align:top;font-size:.82rem}.report-table th{background:#f3f5fa;font-size:.72rem;text-transform:uppercase;white-space:normal}.text-right{text-align:right!important}.empty-report{text-align:center;color:var(--muted);padding:26px}.document-footer{border-top:1px solid var(--border);margin-top:22px;padding-top:12px;color:var(--muted);font-size:.72rem}
@media(max-width:760px){.report-document{padding:16px}.document-head{padding:18px}.document-meta{grid-template-columns:1fr}.question-block{padding:15px}.report-table{min-width:480px}}
@media print{
  .print-toolbar{display:none!important}
  body{background:#fff}
  .report-document{display:block!important;max-width:none;margin:0;border:0;box-shadow:none;padding:0;font-size:11px}
  .document-head{margin-bottom:8px;padding:8px 12px;border-top-width:2px}
  .document-kicker{font-size:.58rem}
  .document-title{font-size:1.05rem;margin:3px 0}
  .document-meta{margin-top:6px;gap:6px}
  .meta-box{padding:5px 8px}
  .meta-label{font-size:.55rem}
  .report-section{margin-top:8px}
  .question-block{margin-top:6px;padding:10px;box-shadow:none}
  .report-table th,.report-table td{padding:5px 7px;font-size:.65rem}
  .document-footer{margin-top:10px;padding-top:6px;font-size:.58rem}
}
</style>
</head>
<body>
<div class="print-toolbar">
    <a class="btn btn-outline btn-sm" href="report_preview.php?survey_id=<?= (int) $survey['id'] ?>">Back to Report</a>
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Print</button>
</div>

<article class="report-document">
    <header class="document-head">
        <div class="document-kicker">Health Center Survey System</div>
        <h1 class="document-title"><?= h($survey['title']) ?></h1>
        <div>Survey Results Report</div>
        <div class="document-meta">
            <div class="meta-box"><div class="meta-label">Response Period</div><div class="meta-value"><?= h(date('M j, Y', strtotime($survey['opens_at']))) ?> to <?= h(date('M j, Y', strtotime($survey['closes_at']))) ?></div></div>
            <div class="meta-box"><div class="meta-label">Total Submissions</div><div class="meta-value"><?= (int) $survey['responses'] ?></div></div>
            <div class="meta-box"><div class="meta-label">Survey Status</div><div class="meta-value"><?= $isOpen ? 'Open' : 'Closed' ?></div></div>
        </div>
    </header>

    <section class="report-section">
        <h2>Question-by-Question Results</h2>
        <?php if (!$questions): ?><p class="empty-report">This survey has no questions.</p><?php endif; ?>
        <?php foreach ($questions as $index => $question): ?>
        <div class="question-block">
            <h3><?= $index + 1 ?>. <?= h($question['question_text']) ?></h3>
            <div class="question-note"><?= h($typeLabels[$question['question_type']] ?? $question['question_type']) ?> | <?= (int) $question['answer_count'] ?> recorded answer<?= (int) $question['answer_count'] === 1 ? '' : 's' ?><?php if ($question['average'] !== null): ?> | Average: <?= h((string) $question['average']) ?> / 5<?php endif; ?></div>

            <?php if (in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)): ?>
            <div class="table-scroll">
            <table class="report-table">
                <colgroup><col class="col-response"><col class="col-count"><col class="col-percent"></colgroup>
                <thead><tr><th>Response</th><th class="text-right">Count</th><th class="text-right">Percentage</th></tr></thead>
                <tbody>
                <?php foreach ($question['rows'] as $row): ?>
                    <tr><td><?= h($row['label']) ?></td><td class="text-right"><?= (int) $row['count'] ?></td><td class="text-right"><?= h(number_format((float) $row['percent'], 1)) ?>%</td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="table-scroll">
            <table class="report-table">
                <colgroup><col style="width:180px"><col></colgroup>
                <thead><tr><th>Submitted</th><th>Written Response</th></tr></thead>
                <tbody>
                <?php foreach ($question['rows'] as $row): ?><tr><td><?= h(date('M j, Y g:i A', strtotime($row['submitted_at']))) ?></td><td><?= nl2br(h($row['answer_text'])) ?></td></tr><?php endforeach; ?>
                <?php if (!$question['rows']): ?><tr><td colspan="2">No written responses.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <footer class="document-footer">Generated <?= h(date('F j, Y, g:i A')) ?> from recorded survey submissions.</footer>
</article>
</body>
</html>
