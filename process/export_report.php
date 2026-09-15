<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/lib/report_builders.php';
require_admin();

$db = database();
$rawSurveyIds = $_GET['survey_id'] ?? [];
if (!is_array($rawSurveyIds)) $rawSurveyIds = [$rawSurveyIds];
$surveyIds = array_values(array_unique(array_filter(array_map('intval', $rawSurveyIds), static fn(int $id): bool => $id > 0)));
$format = (string) ($_GET['format'] ?? 'xlsx');
if (!in_array($format, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Choose either PDF or Excel format.');
}
if (!$surveyIds) {
    http_response_code(400);
    exit('Choose at least one survey to export.');
}

$placeholders = implode(', ', array_fill(0, count($surveyIds), '?'));

$surveyStatement = $db->prepare(
    "SELECT s.id, s.title, s.opens_at, s.closes_at, s.is_active,
            COUNT(DISTINCT sub.id) AS responses
     FROM surveys s
     LEFT JOIN survey_submissions sub ON sub.survey_id = s.id
     WHERE s.id IN (" . $placeholders . ")
     GROUP BY s.id, s.title, s.opens_at, s.closes_at, s.is_active
     ORDER BY s.id DESC"
);
$surveyStatement->execute($surveyIds);
$surveys = $surveyStatement->fetchAll();
if (!$surveys) {
    http_response_code(404);
    exit('The selected survey was not found.');
}

$typeLabels = [
    'multiple_choice' => 'Multiple Choice',
    'yes_no' => 'Yes / No',
    'rating' => 'Rating Scale',
    'short_answer' => 'Written Answer',
];

/**
 * Question + answer breakdown for one survey. Mirrors the logic used by the
 * on-screen report preview so exported numbers always match what admins see.
 */
function fetch_report_questions(PDO $db, int $surveyId): array
{
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
                    'percent' => $total > 0 ? round(((int) $count / $total) * 100, 1) : 0.0,
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

    return $questions;
}

/**
 * Draws one question card (heading + meta line + result table); advances
 * $pdf's cursor. Uses the shared SimplePdfBuilder from report_builders.php.
 */
function render_question_block(SimplePdfBuilder $pdf, int $number, array $question, array $typeLabels, float $marginX, float $contentWidth): void
{
    $titleLines = pdf_wrap($number . '. ' . $question['question_text'], 84);
    $typeLabel = $typeLabels[$question['question_type']] ?? $question['question_type'];
    $metaText = $typeLabel . '  |  ' . (int) $question['answer_count'] . ' recorded answer' . ((int) $question['answer_count'] === 1 ? '' : 's');
    if ($question['average'] !== null) {
        $metaText .= '  |  Average: ' . $question['average'] . ' / 5';
    }

    $isChoice = in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true);
    $rowHeights = [];
    if ($isChoice) {
        $rowCount = max(count($question['rows']), 1);
        for ($i = 0; $i < $rowCount; $i++) $rowHeights[] = 18.0;
    } else {
        if (!$question['rows']) {
            $rowHeights[] = 18.0;
        } else {
            foreach ($question['rows'] as $row) {
                $lines = pdf_wrap((string) $row['answer_text'], 62);
                $rowHeights[] = max(20.0, count($lines) * 12.0 + 8.0);
            }
        }
    }

    $headerBlockHeight = count($titleLines) * 15.0 + 14.0 + 10.0;
    $tableHeaderHeight = 20.0;
    $tableHeight = array_sum($rowHeights);
    $blockHeight = 16.0 + $headerBlockHeight + $tableHeaderHeight + $tableHeight + 16.0;
    $pageUsableHeight = 792.0 - 55.0;
    $drawCard = $blockHeight <= $pageUsableHeight;

    if ($drawCard) {
        $pdf->ensureSpace($blockHeight + 10.0);
    } else {
        $pdf->ensureSpace(min($blockHeight, $pageUsableHeight - 20.0));
    }

    $top = $pdf->getY();
    if ($drawCard) {
        $pdf->rectFill($marginX, $top - $blockHeight, $contentWidth, $blockHeight, [1, 1, 1]);
        $pdf->rectStroke($marginX, $top - $blockHeight, $contentWidth, $blockHeight);
    }

    $cursor = $top - 16.0 - 11.0;
    foreach ($titleLines as $line) {
        $pdf->setY($cursor);
        $pdf->text($marginX + 14, 11, $line, true, [0.08, 0.1, 0.2]);
        $cursor -= 15.0;
    }
    $cursor += 1.0;
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 8.3, $metaText, false, [0.45, 0.48, 0.55]);
    $cursor -= 18.0;

    $tableX = $marginX + 14;
    $tableW = $contentWidth - 28;

    if ($isChoice) {
        $col1 = $tableW * 0.55;
        $col2 = $tableW * 0.20;
        $col3 = $tableW * 0.25;

        $headerY = $cursor;
        $pdf->rectFill($tableX, $headerY - 16, $tableW, 16, [0.953, 0.961, 0.980]);
        $pdf->setY($headerY - 11);
        $pdf->text($tableX + 6, 7.3, 'RESPONSE', true, [0.4, 0.43, 0.5]);
        $pdf->text($tableX + $col1 + $col2 - 32, 7.3, 'COUNT', true, [0.4, 0.43, 0.5]);
        $pdf->text($tableX + $col1 + $col2 + $col3 - 58, 7.3, 'PERCENTAGE', true, [0.4, 0.43, 0.5]);
        $cursor = $headerY - 16;
        $pdf->hLine($tableX, $tableX + $tableW, $cursor);

        $rows = $question['rows'] ?: [['label' => '-', 'count' => 0, 'percent' => 0.0]];
        foreach ($rows as $i => $row) {
            $rowH = $rowHeights[$i] ?? 18.0;
            $rowTop = $cursor;
            $pdf->setY($rowTop - $rowH + 6.0);
            $pdf->text($tableX + 6, 8.6, (string) $row['label']);
            $pdf->text($tableX + $col1 + $col2 - 26, 8.6, (string) $row['count']);
            $pdf->text($tableX + $col1 + $col2 + $col3 - 34, 8.6, number_format((float) $row['percent'], 1) . '%');
            $cursor -= $rowH;
            $pdf->hLine($tableX, $tableX + $tableW, $cursor);
        }
    } else {
        $col1 = 120.0;
        $headerY = $cursor;
        $pdf->rectFill($tableX, $headerY - 16, $tableW, 16, [0.953, 0.961, 0.980]);
        $pdf->setY($headerY - 11);
        $pdf->text($tableX + 6, 7.3, 'SUBMITTED', true, [0.4, 0.43, 0.5]);
        $pdf->text($tableX + $col1 + 6, 7.3, 'WRITTEN RESPONSE', true, [0.4, 0.43, 0.5]);
        $cursor = $headerY - 16;
        $pdf->hLine($tableX, $tableX + $tableW, $cursor);

        if (!$question['rows']) {
            $pdf->setY($cursor - 12.0);
            $pdf->text($tableX + 6, 8.6, 'No written responses.');
            $cursor -= 18.0;
            $pdf->hLine($tableX, $tableX + $tableW, $cursor);
        } else {
            foreach ($question['rows'] as $i => $row) {
                $rowH = $rowHeights[$i] ?? 20.0;
                $rowTop = $cursor;
                $submitted = date('M j, Y g:i A', strtotime((string) $row['submitted_at']));
                $lines = pdf_wrap((string) $row['answer_text'], 62);
                $pdf->setY($rowTop - 12.0);
                $pdf->text($tableX + 6, 8.3, $submitted);
                $lineY = $rowTop - 12.0;
                foreach ($lines as $line) {
                    $pdf->setY($lineY);
                    $pdf->text($tableX + $col1 + 6, 8.3, $line);
                    $lineY -= 12.0;
                }
                $cursor -= $rowH;
                $pdf->hLine($tableX, $tableX + $tableW, $cursor);
            }
        }
    }

    $pdf->setY($top - $blockHeight - 14.0);
}

function render_survey_pdf(array $survey, array $questions, array $typeLabels): string
{
    $pdf = new SimplePdfBuilder();
    $marginX = 50.0;
    $contentWidth = 495.0;

    $isOpen = (int) $survey['is_active'] === 1
        && date('Y-m-d') >= $survey['opens_at']
        && date('Y-m-d') < $survey['closes_at'];

    $titleLines = pdf_wrap($survey['title'], 58);
    $period = date('M j, Y', strtotime($survey['opens_at'])) . ' to ' . date('M j, Y', strtotime($survey['closes_at']));
    $metaLine = 'Response Period: ' . $period . '     Total Submissions: ' . (int) $survey['responses'] . '     Survey Status: ' . ($isOpen ? 'Open' : 'Closed');

    $headerHeight = 24.0 + 15.0 + count($titleLines) * 20.0 + 16.0 + 14.0;
    $pdf->ensureSpace($headerHeight + 30);
    $top = $pdf->getY();

    $pdf->rectFill($marginX, $top - $headerHeight, $contentWidth, $headerHeight, [0.968, 0.973, 0.996]);
    $pdf->rectFill($marginX, $top - 3, $contentWidth, 3, [0.161, 0.239, 0.616]);
    $pdf->rectStroke($marginX, $top - $headerHeight, $contentWidth, $headerHeight);

    $cursor = $top - 18.0;
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 7.5, 'HEALTH CENTER SURVEY SYSTEM', true, [0.32, 0.43, 0.86]);
    $cursor -= 20.0;
    foreach ($titleLines as $line) {
        $pdf->setY($cursor);
        $pdf->text($marginX + 14, 15, $line, true, [0.06, 0.08, 0.16]);
        $cursor -= 19.0;
    }
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 9.5, 'Survey Results Report', false, [0.36, 0.4, 0.5]);
    $cursor -= 22.0;
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 8.8, $metaLine, false, [0.16, 0.18, 0.28]);

    $pdf->setY($top - $headerHeight - 26.0);

    $pdf->ensureSpace(26);
    $pdf->text($marginX, 12.5, 'Question-by-Question Results', true, [0.06, 0.08, 0.16]);
    $pdf->moveY(6);
    $pdf->hLine($marginX, $marginX + $contentWidth, $pdf->getY());
    $pdf->moveY(16);

    if (!$questions) {
        $pdf->text($marginX, 9.5, 'This survey has no questions.', false, [0.5, 0.53, 0.6]);
        $pdf->moveY(20);
    }

    foreach ($questions as $index => $question) {
        render_question_block($pdf, $index + 1, $question, $typeLabels, $marginX, $contentWidth);
    }

    $pdf->ensureSpace(20);
    $pdf->text($marginX, 8, 'Generated ' . date('F j, Y, g:i A') . ' from recorded survey submissions.', false, [0.5, 0.53, 0.6]);

    return $pdf->output();
}

function render_survey_xlsx(array $survey, array $questions, array $typeLabels): string
{
    $isOpen = (int) $survey['is_active'] === 1
        && date('Y-m-d') >= $survey['opens_at']
        && date('Y-m-d') < $survey['closes_at'];

    $sheet = new XlsxSheetBuilder();
    $sheet->addRow([['value' => 'HEALTH CENTER SURVEY SYSTEM', 'style' => XlsxStyle::KICKER]]);
    $sheet->addRow([['value' => $survey['title'], 'style' => XlsxStyle::TITLE]]);
    $sheet->addRow([['value' => 'Survey Results Report', 'style' => XlsxStyle::SUBTITLE]]);
    $sheet->blankRow();
    $sheet->addRow([
        ['value' => 'Response Period', 'style' => XlsxStyle::LABEL],
        ['value' => date('M j, Y', strtotime($survey['opens_at'])) . ' to ' . date('M j, Y', strtotime($survey['closes_at'])), 'style' => XlsxStyle::VALUE],
    ]);
    $sheet->addRow([
        ['value' => 'Total Submissions', 'style' => XlsxStyle::LABEL],
        ['value' => (int) $survey['responses'], 'style' => XlsxStyle::VALUE],
    ]);
    $sheet->addRow([
        ['value' => 'Survey Status', 'style' => XlsxStyle::LABEL],
        ['value' => $isOpen ? 'Open' : 'Closed', 'style' => XlsxStyle::VALUE],
    ]);
    $sheet->blankRow();
    $sheet->addRow([['value' => 'Question-by-Question Results', 'style' => XlsxStyle::SECTION_HEADING]]);
    $sheet->blankRow();

    if (!$questions) {
        $sheet->addRow([['value' => 'This survey has no questions.', 'style' => XlsxStyle::META]]);
    }

    foreach ($questions as $index => $question) {
        $typeLabel = $typeLabels[$question['question_type']] ?? $question['question_type'];
        $sheet->addRow([['value' => ($index + 1) . '. ' . $question['question_text'], 'style' => XlsxStyle::QUESTION_TITLE]]);
        $metaText = $typeLabel . '  |  ' . (int) $question['answer_count'] . ' recorded answer' . ((int) $question['answer_count'] === 1 ? '' : 's');
        if ($question['average'] !== null) $metaText .= '  |  Average: ' . $question['average'] . ' / 5';
        $sheet->addRow([['value' => $metaText, 'style' => XlsxStyle::META]]);

        if (in_array($question['question_type'], ['multiple_choice', 'yes_no', 'rating'], true)) {
            $headerRow = $sheet->currentRowNumber() + 1;
            $sheet->addRow([
                ['value' => 'Response', 'style' => XlsxStyle::TABLE_HEADER],
                ['value' => 'Count', 'style' => XlsxStyle::TABLE_HEADER_RIGHT],
                ['value' => 'Percentage', 'style' => XlsxStyle::TABLE_HEADER_RIGHT],
            ]);
            $rows = $question['rows'] ?: [['label' => '-', 'count' => 0, 'percent' => 0.0]];
            foreach ($rows as $row) {
                $sheet->addRow([
                    ['value' => $row['label'], 'style' => XlsxStyle::DATA_CELL],
                    ['value' => (int) $row['count'], 'style' => XlsxStyle::DATA_CELL_COUNT, 'numeric' => true],
                    ['value' => (float) $row['percent'], 'style' => XlsxStyle::DATA_CELL_PERCENT, 'numeric' => true],
                ]);
            }
            $sheet->markTable($headerRow, $sheet->currentRowNumber(), ['Response', 'Count', 'Percentage']);
        } else {
            $headerRow = $sheet->currentRowNumber() + 1;
            $sheet->addRow([
                ['value' => 'Submitted', 'style' => XlsxStyle::TABLE_HEADER],
                ['value' => 'Written Response', 'style' => XlsxStyle::TABLE_HEADER],
            ]);
            if (!$question['rows']) {
                $sheet->addRow([
                    ['value' => 'No written responses.', 'style' => XlsxStyle::DATA_CELL],
                    ['value' => '', 'style' => XlsxStyle::DATA_CELL],
                ]);
            } else {
                foreach ($question['rows'] as $row) {
                    $sheet->addRow([
                        ['value' => date('M j, Y g:i A', strtotime((string) $row['submitted_at'])), 'style' => XlsxStyle::DATA_CELL],
                        ['value' => (string) $row['answer_text'], 'style' => XlsxStyle::DATA_CELL],
                    ]);
                }
            }
            $sheet->markTable($headerRow, $sheet->currentRowNumber(), ['Submitted', 'Written Response']);
        }
        $sheet->blankRow();
    }

    $sheet->addRow([['value' => 'Generated ' . date('F j, Y, g:i A') . ' from recorded survey submissions.', 'style' => XlsxStyle::FOOTER]]);

    // ------------------------------------------------------------------
    // Turn every recorded response block into a real Excel Table (banded
    // rows, header filter dropdowns, a named range) instead of plain
    // bordered cells, so the "Question-by-Question Results" section
    // actually looks and behaves like a table when opened in Excel.
    // ------------------------------------------------------------------
    $tableDefs = $sheet->getTables();
    $tableParts = '';
    $tableFiles = [];
    $worksheetRels = '';
    $tableContentTypeOverrides = '';
    $tableNum = 0;

    foreach ($tableDefs as $table) {
        $tableNum++;
        $lastCol = xlsx_col_letter(count($table['columns']));
        $ref = 'A' . $table['headerRow'] . ':' . $lastCol . $table['lastRow'];

        $columnsXml = '';
        foreach ($table['columns'] as $i => $columnName) {
            $columnsXml .= '<tableColumn id="' . ($i + 1) . '" name="' . xlsx_escape($columnName) . '"/>';
        }

        $tableFiles['xl/tables/table' . $tableNum . '.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="' . $tableNum . '" name="QuestionTable' . $tableNum . '" displayName="QuestionTable' . $tableNum . '" ref="' . $ref . '" totalsRowShown="0">'
            . '<autoFilter ref="' . $ref . '"/>'
            . '<tableColumns count="' . count($table['columns']) . '">' . $columnsXml . '</tableColumns>'
            . '<tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/>'
            . '</table>';

        $rId = 'rId' . $tableNum;
        $tableParts .= '<tablePart r:id="' . $rId . '"/>';
        $worksheetRels .= '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table' . $tableNum . '.xml"/>';
        $tableContentTypeOverrides .= '<Override PartName="/xl/tables/table' . $tableNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<cols><col min="1" max="1" width="56" customWidth="1"/><col min="2" max="2" width="16" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/></cols>'
        . '<sheetData>' . $sheet->toXml() . '</sheetData>'
        . ($tableParts !== '' ? '<tableParts count="' . $tableNum . '">' . $tableParts . '</tableParts>' : '')
        . '</worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . $tableContentTypeOverrides
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Survey Report" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $files = [
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels' => $rootRels,
        'xl/workbook.xml' => $workbookXml,
        'xl/_rels/workbook.xml.rels' => $workbookRels,
        'xl/styles.xml' => xlsx_styles_xml(),
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    if ($worksheetRels !== '') {
        $files['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $worksheetRels
            . '</Relationships>';
    }
    foreach ($tableFiles as $path => $data) {
        $files[$path] = $data;
    }

    return build_zip_bytes($files);
}

// ---------------------------------------------------------------------
// Build one file per selected survey, then either stream it directly
// (single survey) or bundle everything into a ZIP (multiple surveys) so
// every report stays in its own, correctly-formatted file.
// ---------------------------------------------------------------------
$dateStamp = date('Y-m-d');
$generatedFiles = [];
foreach ($surveys as $survey) {
    $questions = fetch_report_questions($db, (int) $survey['id']);
    $slug = slugify_filename($survey['title'], 'survey');
    if ($format === 'pdf') {
        $data = render_survey_pdf($survey, $questions, $typeLabels);
        $filename = $slug . '-' . $dateStamp . '.pdf';
    } else {
        $data = render_survey_xlsx($survey, $questions, $typeLabels);
        $filename = $slug . '-' . $dateStamp . '.xlsx';
    }
    $generatedFiles[$filename] = $data;
}

if (count($generatedFiles) === 1) {
    $filename = array_key_first($generatedFiles);
    $data = $generatedFiles[$filename];
    $mime = $format === 'pdf'
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

$zipData = build_zip_bytes($generatedFiles);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="survey-reports-' . $dateStamp . '.zip"');
header('Content-Length: ' . strlen($zipData));
echo $zipData;
exit;