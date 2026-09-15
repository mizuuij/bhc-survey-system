<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/admin_auth.php';
require_admin();

$db = database();
$rawResidentIds = $_GET['resident_id'] ?? [];
if (!is_array($rawResidentIds)) $rawResidentIds = [$rawResidentIds];
$residentIds = array_values(array_unique(array_filter(array_map('intval', $rawResidentIds), static fn(int $id): bool => $id > 0)));
$format = (string) ($_GET['format'] ?? 'xlsx');
if (!in_array($format, ['xlsx', 'pdf'], true)) {
    http_response_code(400);
    exit('Choose either PDF or Excel format.');
}
if (!$residentIds) {
    http_response_code(400);
    exit('Choose at least one member to export.');
}

$civilStatusLabels = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'divorced' => 'Divorced'];

/**
 * Resident + profile + family breakdown for one member. Mirrors the logic
 * used by the on-screen report preview so exported data always matches what
 * admins see.
 */
function fetch_member_report_data(PDO $db, int $residentId): ?array
{
    $residentStatement = $db->prepare(
        "SELECT r.id, r.resident_number, r.household_number, r.head_name, r.contact_number, r.address,
                r.purok, r.is_active, r.created_at, rp.civil_status, rp.birthday
         FROM residents r
         LEFT JOIN resident_profile rp ON rp.resident_id = r.id
         WHERE r.id = ? AND r.archived_at IS NULL
         LIMIT 1"
    );
    $residentStatement->execute([$residentId]);
    $resident = $residentStatement->fetch();
    if (!$resident) {
        return null;
    }

    $profileStatement = $db->prepare('SELECT * FROM resident_profile WHERE resident_id = ? LIMIT 1');
    $profileStatement->execute([$residentId]);
    $profile = $profileStatement->fetch() ?: null;

    $spouseStatement = $db->prepare('SELECT * FROM resident_spouse WHERE resident_id = ? LIMIT 1');
    $spouseStatement->execute([$residentId]);
    $spouse = $spouseStatement->fetch() ?: null;

    $parentsStatement = $db->prepare('SELECT * FROM resident_parents WHERE resident_id = ? LIMIT 1');
    $parentsStatement->execute([$residentId]);
    $parents = $parentsStatement->fetch() ?: null;

    $childrenStatement = $db->prepare('SELECT child_name, age FROM resident_children WHERE resident_id = ? ORDER BY id');
    $childrenStatement->execute([$residentId]);
    $children = $childrenStatement->fetchAll();

    $referencesStatement = $db->prepare('SELECT reference_name FROM resident_references WHERE resident_id = ? ORDER BY id');
    $referencesStatement->execute([$residentId]);
    $references = $referencesStatement->fetchAll();

    $age = $resident['birthday'] ? (int) (new DateTime($resident['birthday']))->diff(new DateTime())->y : null;

    $fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['middle_name'] ?? '') . ' ' . ($profile['last_name'] ?? '') . ' ' . ($profile['extension_name'] ?? ''));
    $fullName = $fullName !== '' ? (preg_replace('/\s+/', ' ', $fullName) ?? $fullName) : $resident['head_name'];

    return [
        'resident' => $resident,
        'profile' => $profile,
        'spouse' => $spouse,
        'parents' => $parents,
        'children' => $children,
        'references' => $references,
        'age' => $age,
        'full_name' => $fullName,
    ];
}

/**
 * Builds the "Household & Contact Info", "Personal Profile", "Family
 * Information", and "Character References" sections as plain label/value
 * (or single-column) row lists, ready to be laid out by either the PDF or
 * XLSX renderer below.
 *
 * @return array<int, array{title: string, rows: list<array{label?: string, value: string}>}>
 */
function build_member_sections(array $data, array $civilStatusLabels): array
{
    $resident = $data['resident'];
    $profile = $data['profile'];
    $spouse = $data['spouse'];
    $parents = $data['parents'];

    $household = [
        ['label' => 'Household No.', 'value' => (string) $resident['household_number']],
        ['label' => 'Contact Number', 'value' => (string) $resident['contact_number']],
        ['label' => 'Address', 'value' => (string) $resident['address']],
        ['label' => 'Purok', 'value' => $resident['purok'] ?: 'Not set'],
        ['label' => 'Registered', 'value' => date('M j, Y', strtotime((string) $resident['created_at']))],
    ];

    $personal = [];
    if ($profile === null) {
        $personal[] = ['value' => 'This member has not completed their personal profile yet.'];
    } else {
        $personal = [
            ['label' => 'Full Name', 'value' => $data['full_name']],
            ['label' => 'Civil Status', 'value' => $profile['civil_status'] ? ($civilStatusLabels[$profile['civil_status']] ?? ucfirst((string) $profile['civil_status'])) : 'Not set'],
            ['label' => 'Birth of Date', 'value' => $profile['birthday'] ? date('M j, Y', strtotime((string) $profile['birthday'])) : 'Not set'],
            ['label' => 'Age', 'value' => $data['age'] !== null ? (string) $data['age'] : 'Not set'],
            ['label' => 'Occupation', 'value' => $profile['occupation'] ?: 'Not set'],
            ['label' => 'Employer', 'value' => $profile['employer'] ?: 'Not set'],
            ['label' => 'Employer Address', 'value' => $profile['employer_address'] ?: 'Not set'],
        ];
    }

    $family = [];
    if ($spouse !== null) {
        $spouseValue = (string) $spouse['spouse_name'];
        if (!empty($spouse['occupation'])) $spouseValue .= ' — ' . $spouse['occupation'];
        $family[] = ['label' => 'Spouse', 'value' => $spouseValue];
    }
    if ($parents !== null) {
        $family[] = ['label' => "Father's Name", 'value' => $parents['father_name'] ?: 'Not set'];
        $family[] = ['label' => "Mother's Name", 'value' => $parents['mother_name'] ?: 'Not set'];
    }
    if ($data['children']) {
        $childList = implode(', ', array_map(
            static fn(array $c): string => $c['child_name'] . ($c['age'] !== null ? ' (' . (int) $c['age'] . ')' : ''),
            $data['children']
        ));
        $family[] = ['label' => 'Children', 'value' => $childList];
    }
    if (!$family) {
        $family[] = ['value' => 'No family information on file.'];
    }

    $references = [];
    if ($data['references']) {
        foreach ($data['references'] as $ref) {
            $references[] = ['value' => (string) $ref['reference_name']];
        }
    } else {
        $references[] = ['value' => 'No references on file.'];
    }

    return [
        ['title' => 'Household & Contact Information', 'rows' => $household],
        ['title' => 'Personal Profile', 'rows' => $personal],
        ['title' => 'Family Information', 'rows' => $family],
        ['title' => 'Character References', 'rows' => $references],
    ];
}

function slugify_filename(string $text): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '';
    $slug = strtolower(trim($slug, '-'));
    return $slug !== '' ? $slug : 'member';
}

/** @return list<string> */
function pdf_wrap(string $text, int $length = 92): array
{
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    if ($text === '') return [''];
    $wrapped = wordwrap($text, $length, "\n", true);
    return explode("\n", $wrapped);
}

function pdf_escape(string $text): string
{
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    $text = $converted === false ? $text : $converted;
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
}

/**
 * Minimal, dependency-free PDF writer that supports multiple pages, two
 * fonts (Helvetica / Helvetica-Bold), coloured text, filled rectangles and
 * stroked lines/rectangles - just enough drawing primitives to lay the
 * report out like the on-screen preview instead of a flat text dump.
 * (Mirrors the survey report's SimplePdfBuilder.)
 */
final class SimplePdfBuilder
{
    private const PAGE_W = 595.0;
    private const PAGE_H = 842.0;
    private const TOP = 792.0;
    private const BOTTOM = 55.0;

    private array $pages = [];
    private string $content = '';
    private float $y = self::TOP;

    public function newPage(): void
    {
        $this->pages[] = $this->content;
        $this->content = '';
        $this->y = self::TOP;
    }

    public function getY(): float { return $this->y; }
    public function setY(float $y): void { $this->y = $y; }
    public function moveY(float $delta): void { $this->y -= $delta; }

    public function ensureSpace(float $height): void
    {
        if ($this->y - $height < self::BOTTOM) {
            $this->newPage();
        }
    }

    public function text(float $x, float $size, string $text, bool $bold = false, array $color = [0.09, 0.11, 0.2]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $esc = pdf_escape($text);
        [$r, $g, $b] = $color;
        $this->content .= sprintf(
            "BT\n%.3f %.3f %.3f rg\n/%s %.2f Tf\n%.2f %.2f Td\n(%s) Tj\nET\n",
            $r, $g, $b, $font, $size, $x, $this->y, $esc
        );
    }

    public function rectFill(float $x, float $y, float $w, float $h, array $color): void
    {
        [$r, $g, $b] = $color;
        $this->content .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n", $r, $g, $b, $x, $y, $w, $h);
    }

    public function rectStroke(float $x, float $y, float $w, float $h, array $color = [0.82, 0.85, 0.91], float $lineWidth = 0.8): void
    {
        [$r, $g, $b] = $color;
        $this->content .= sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f %.2f %.2f re S\n", $r, $g, $b, $lineWidth, $x, $y, $w, $h);
    }

    public function hLine(float $x1, float $x2, float $y, array $color = [0.82, 0.85, 0.91], float $lineWidth = 0.8): void
    {
        [$r, $g, $b] = $color;
        $this->content .= sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f m %.2f %.2f l S\n", $r, $g, $b, $lineWidth, $x1, $y, $x2, $y);
    }

    public function output(): string
    {
        $this->pages[] = $this->content;
        $this->content = '';

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageIds = [];
        $objectId = 5;
        foreach ($this->pages as $pageStream) {
            $pageId = $objectId++;
            $contentId = $objectId++;
            $pageIds[] = $pageId;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($pageStream) . " >>\nstream\n" . $pageStream . "\nendstream";
        }
        if (!$pageIds) {
            $pageId = $objectId++;
            $contentId = $objectId++;
            $pageIds[] = $pageId;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_W . ' ' . self::PAGE_H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length 0 >>\nstream\n\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
        return $pdf;
    }
}

/** Draws one label/value (or single-column) section card; advances $pdf's cursor. */
function render_member_section_block(SimplePdfBuilder $pdf, array $section, float $marginX, float $contentWidth): void
{
    $titleLines = pdf_wrap($section['title'], 84);
    $rows = $section['rows'];
    $hasLabels = isset($rows[0]['label']);

    $rowHeights = [];
    foreach ($rows as $row) {
        $lines = pdf_wrap((string) $row['value'], $hasLabels ? 62 : 90);
        $rowHeights[] = max(20.0, count($lines) * 12.0 + 8.0);
    }

    $headerBlockHeight = count($titleLines) * 15.0 + 14.0;
    $tableHeight = array_sum($rowHeights);
    $blockHeight = 16.0 + $headerBlockHeight + $tableHeight + 16.0;
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
    $cursor -= 4.0;

    $tableX = $marginX + 14;
    $tableW = $contentWidth - 28;
    $col1 = $hasLabels ? 150.0 : 0.0;

    foreach ($rows as $i => $row) {
        $rowH = $rowHeights[$i] ?? 20.0;
        $rowTop = $cursor;
        $lines = pdf_wrap((string) $row['value'], $hasLabels ? 62 : 90);
        if ($hasLabels && isset($row['label'])) {
            $pdf->setY($rowTop - 12.0);
            $pdf->text($tableX, 8.6, (string) $row['label'], true, [0.35, 0.38, 0.46]);
        }
        $lineY = $rowTop - 12.0;
        foreach ($lines as $line) {
            $pdf->setY($lineY);
            $pdf->text($tableX + $col1, 8.6, $line);
            $lineY -= 12.0;
        }
        $cursor -= $rowH;
        $pdf->hLine($tableX, $tableX + $tableW, $cursor);
    }

    $pdf->setY($top - $blockHeight - 14.0);
}

function render_member_pdf(array $data): string
{
    $pdf = new SimplePdfBuilder();
    $marginX = 50.0;
    $contentWidth = 495.0;
    $resident = $data['resident'];

    $titleLines = pdf_wrap((string) $resident['head_name'], 58);
    $metaLine = 'Account No: ' . $resident['resident_number'] . '     Purok: ' . ($resident['purok'] ?: 'Not set') . '     Status: ' . ((int) $resident['is_active'] === 1 ? 'Active' : 'Deactivated');

    $headerHeight = 24.0 + 15.0 + count($titleLines) * 20.0 + 16.0 + 14.0;
    $pdf->ensureSpace($headerHeight + 30);
    $top = $pdf->getY();

    $pdf->rectFill($marginX, $top - $headerHeight, $contentWidth, $headerHeight, [0.968, 0.973, 0.996]);
    $pdf->rectFill($marginX, $top - 3, $contentWidth, 3, [0.161, 0.239, 0.616]);
    $pdf->rectStroke($marginX, $top - $headerHeight, $contentWidth, $headerHeight);

    $cursor = $top - 18.0;
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 7.5, 'BARANGAY LONGOS SURVEY SYSTEM', true, [0.32, 0.43, 0.86]);
    $cursor -= 20.0;
    foreach ($titleLines as $line) {
        $pdf->setY($cursor);
        $pdf->text($marginX + 14, 15, $line, true, [0.06, 0.08, 0.16]);
        $cursor -= 19.0;
    }
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 9.5, 'Member Profile Report', false, [0.36, 0.4, 0.5]);
    $cursor -= 22.0;
    $pdf->setY($cursor);
    $pdf->text($marginX + 14, 8.8, $metaLine, false, [0.16, 0.18, 0.28]);

    $pdf->setY($top - $headerHeight - 26.0);

    foreach (build_member_sections($data, $GLOBALS['civilStatusLabels']) as $section) {
        render_member_section_block($pdf, $section, $marginX, $contentWidth);
    }

    $pdf->ensureSpace(20);
    $pdf->text($marginX, 8, 'Generated ' . date('F j, Y, g:i A') . ' from the barangay resident registry.', false, [0.5, 0.53, 0.6]);

    return $pdf->output();
}

/**
 * Zero-dependency ZIP writer (stored/uncompressed entries) so exports work
 * even on hosts without the zip extension enabled. Used both for the outer
 * "multiple members" bundle and, internally, to package each .xlsx file
 * (an .xlsx is itself just a zip of XML parts).
 */
final class SimpleZipWriter
{
    private array $files = [];

    public function addFile(string $name, string $data): void
    {
        $this->files[$name] = $data;
    }

    public function output(): string
    {
        $localEntries = '';
        $centralDirectory = '';
        $offset = 0;
        $count = 0;
        $dosTime = pack('v', 0);
        $dosDate = pack('v', (33 << 9) | (1 << 5) | 1); // fixed placeholder date

        foreach ($this->files as $name => $data) {
            $count++;
            $crc = crc32($data);
            $length = strlen($data);
            $nameLength = strlen($name);

            $localHeader = "PK\x03\x04"
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . $dosTime . $dosDate
                . pack('V', $crc)
                . pack('V', $length)
                . pack('V', $length)
                . pack('v', $nameLength)
                . pack('v', 0)
                . $name;
            $localEntries .= $localHeader . $data;

            $centralDirectory .= "PK\x01\x02"
                . pack('v', 20)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . $dosTime . $dosDate
                . pack('V', $crc)
                . pack('V', $length)
                . pack('V', $length)
                . pack('v', $nameLength)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', $offset)
                . $name;

            $offset += strlen($localHeader) + $length;
        }

        $centralDirOffset = $offset;
        $centralDirSize = strlen($centralDirectory);
        $endRecord = "PK\x05\x06"
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', $count)
            . pack('v', $count)
            . pack('V', $centralDirSize)
            . pack('V', $centralDirOffset)
            . pack('v', 0);

        return $localEntries . $centralDirectory . $endRecord;
    }
}

/** Builds a zip archive (as raw bytes) from a [filename => data] map, preferring the native extension when available. */
function build_zip_bytes(array $files): string
{
    if (class_exists('ZipArchive')) {
        $tmpPath = tempnam(sys_get_temp_dir(), 'bhc_zip_');
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE) === true) {
            foreach ($files as $name => $data) {
                $zip->addFromString($name, $data);
            }
            $zip->close();
            $bytes = file_get_contents($tmpPath);
            unlink($tmpPath);
            if ($bytes !== false) {
                return $bytes;
            }
        }
    }

    $writer = new SimpleZipWriter();
    foreach ($files as $name => $data) {
        $writer->addFile($name, $data);
    }
    return $writer->output();
}

function xlsx_escape(string $text): string
{
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
    return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'], $text);
}

function xlsx_col_letter(int $col): string
{
    $letter = '';
    while ($col > 0) {
        $rem = ($col - 1) % 26;
        $letter = chr(65 + $rem) . $letter;
        $col = intdiv($col - 1, 26);
    }
    return $letter;
}

/**
 * Cell style indices, matched to the cellXfs list defined in
 * xlsx_styles_xml() below. Keep both in sync.
 */
final class XlsxStyle
{
    public const DEFAULT_ = 0;
    public const KICKER = 1;
    public const TITLE = 2;
    public const SUBTITLE = 3;
    public const LABEL = 4;
    public const VALUE = 5;
    public const SECTION_HEADING = 6;
    public const FIELD_LABEL = 7;
    public const DATA_CELL = 8;
    public const FOOTER = 9;
}

function xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="8">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF5470DC"/><sz val="9"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF161A2C"/><sz val="16"/><name val="Calibri"/></font>'
        . '<font><i/><color rgb="FF6B7280"/><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF1F2430"/><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF141826"/><sz val="13"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF141826"/><sz val="10"/><name val="Calibri"/></font>'
        . '<font><i/><color rgb="FF6B7280"/><sz val="9"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F8FE"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFD9DEEA"/></left><right style="thin"><color rgb="FFD9DEEA"/></right><top style="thin"><color rgb="FFD9DEEA"/></top><bottom style="thin"><color rgb="FFD9DEEA"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="10">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0 default
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 1 kicker
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 2 title
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 3 subtitle
        . '<xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' // 4 label
        . '<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/>' // 5 value
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 6 section heading
        . '<xf numFmtId="0" fontId="6" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' // 7 field label
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' // 8 data cell
        . '<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 9 footer
        . '</cellXfs>'
        . '</styleSheet>';
}

final class XlsxSheetBuilder
{
    private array $rows = [];
    private int $currentRow = 0;
    /** @var list<array{headerRow: int, lastRow: int, columns: list<string>}> */
    private array $tables = [];

    /** @param list<array{value: string|int|float, style?: int}> $cells */
    public function addRow(array $cells): void
    {
        $this->currentRow++;
        $this->rows[$this->currentRow] = $cells;
    }

    public function blankRow(): void
    {
        $this->currentRow++;
    }

    public function currentRowNumber(): int
    {
        return $this->currentRow;
    }

    /** @param list<string> $columns */
    public function markTable(int $headerRow, int $lastRow, array $columns): void
    {
        if ($lastRow < $headerRow) {
            return;
        }
        $this->tables[] = ['headerRow' => $headerRow, 'lastRow' => $lastRow, 'columns' => $columns];
    }

    /** @return list<array{headerRow: int, lastRow: int, columns: list<string>}> */
    public function getTables(): array
    {
        return $this->tables;
    }

    public function toXml(): string
    {
        $xml = '';
        foreach ($this->rows as $r => $cells) {
            $xml .= '<row r="' . $r . '">';
            $col = 1;
            foreach ($cells as $cell) {
                $ref = xlsx_col_letter($col) . $r;
                $style = $cell['style'] ?? XlsxStyle::DEFAULT_;
                $value = (string) ($cell['value'] ?? '');
                if ($value !== '') {
                    $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . xlsx_escape($value) . '</t></is></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"/>';
                }
                $col++;
            }
            $xml .= '</row>';
        }
        return $xml;
    }
}

function render_member_xlsx(array $data): string
{
    $resident = $data['resident'];

    $sheet = new XlsxSheetBuilder();
    $sheet->addRow([['value' => 'BARANGAY LONGOS SURVEY SYSTEM', 'style' => XlsxStyle::KICKER]]);
    $sheet->addRow([['value' => $resident['head_name'], 'style' => XlsxStyle::TITLE]]);
    $sheet->addRow([['value' => 'Member Profile Report', 'style' => XlsxStyle::SUBTITLE]]);
    $sheet->blankRow();
    $sheet->addRow([
        ['value' => 'Account No.', 'style' => XlsxStyle::LABEL],
        ['value' => (string) $resident['resident_number'], 'style' => XlsxStyle::VALUE],
    ]);
    $sheet->addRow([
        ['value' => 'Purok', 'style' => XlsxStyle::LABEL],
        ['value' => $resident['purok'] ?: 'Not set', 'style' => XlsxStyle::VALUE],
    ]);
    $sheet->addRow([
        ['value' => 'Status', 'style' => XlsxStyle::LABEL],
        ['value' => (int) $resident['is_active'] === 1 ? 'Active' : 'Deactivated', 'style' => XlsxStyle::VALUE],
    ]);
    $sheet->blankRow();

    foreach (build_member_sections($data, $GLOBALS['civilStatusLabels']) as $section) {
        $sheet->addRow([['value' => $section['title'], 'style' => XlsxStyle::SECTION_HEADING]]);
        $headerRow = $sheet->currentRowNumber() + 1;
        $hasLabels = isset($section['rows'][0]['label']);
        if ($hasLabels) {
            $sheet->addRow([
                ['value' => 'Field', 'style' => XlsxStyle::FIELD_LABEL],
                ['value' => 'Details', 'style' => XlsxStyle::FIELD_LABEL],
            ]);
            foreach ($section['rows'] as $row) {
                $sheet->addRow([
                    ['value' => (string) ($row['label'] ?? ''), 'style' => XlsxStyle::FIELD_LABEL],
                    ['value' => (string) $row['value'], 'style' => XlsxStyle::DATA_CELL],
                ]);
            }
            $sheet->markTable($headerRow, $sheet->currentRowNumber(), ['Field', 'Details']);
        } else {
            $sheet->addRow([['value' => 'Details', 'style' => XlsxStyle::FIELD_LABEL]]);
            foreach ($section['rows'] as $row) {
                $sheet->addRow([['value' => (string) $row['value'], 'style' => XlsxStyle::DATA_CELL]]);
            }
            $sheet->markTable($headerRow, $sheet->currentRowNumber(), ['Details']);
        }
        $sheet->blankRow();
    }

    $sheet->addRow([['value' => 'Generated ' . date('F j, Y, g:i A') . ' from the barangay resident registry.', 'style' => XlsxStyle::FOOTER]]);

    // ------------------------------------------------------------------
    // Turn every section into a real Excel Table (banded rows, header
    // filter dropdowns, a named range) instead of plain bordered cells.
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
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="' . $tableNum . '" name="MemberTable' . $tableNum . '" displayName="MemberTable' . $tableNum . '" ref="' . $ref . '" totalsRowShown="0">'
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
        . '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="56" customWidth="1"/></cols>'
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
        . '<sheets><sheet name="Member Report" sheetId="1" r:id="rId1"/></sheets>'
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
    foreach ($tableFiles as $path => $fileData) {
        $files[$path] = $fileData;
    }

    return build_zip_bytes($files);
}

// ---------------------------------------------------------------------
// Build one file per selected member, then either stream it directly
// (single member) or bundle everything into a ZIP (multiple members) so
// every report stays in its own, correctly-formatted file - exactly the
// same flow as the survey report export.
// ---------------------------------------------------------------------
$dateStamp = date('Y-m-d');
$generatedFiles = [];
foreach ($residentIds as $residentId) {
    $data = fetch_member_report_data($db, $residentId);
    if ($data === null) {
        continue;
    }
    $slug = slugify_filename((string) $data['resident']['head_name']);
    if ($format === 'pdf') {
        $fileData = render_member_pdf($data);
        $filename = $slug . '-' . $dateStamp . '.pdf';
    } else {
        $fileData = render_member_xlsx($data);
        $filename = $slug . '-' . $dateStamp . '.xlsx';
    }
    $generatedFiles[$filename] = $fileData;
}

if (!$generatedFiles) {
    http_response_code(404);
    exit('The selected member(s) could not be found.');
}

if (count($generatedFiles) === 1) {
    $filename = array_key_first($generatedFiles);
    $fileData = $generatedFiles[$filename];
    $mime = $format === 'pdf'
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($fileData));
    echo $fileData;
    exit;
}

$zipData = build_zip_bytes($generatedFiles);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="member-reports-' . $dateStamp . '.zip"');
header('Content-Length: ' . strlen($zipData));
echo $zipData;
exit;