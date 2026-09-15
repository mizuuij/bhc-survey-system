<?php
declare(strict_types=1);

/**
 * Shared PDF/XLSX/ZIP building blocks for process/export_report.php.
 * Mirrors the equivalents in process/export_member_report.php, extended
 * with the extra XLSX styles (question titles, meta lines, response
 * tables with numeric count/percentage columns) that the survey report
 * needs but the member report doesn't.
 */

function slugify_filename(string $text, string $fallback = 'file'): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '';
    $slug = strtolower(trim($slug, '-'));
    return $slug !== '' ? $slug : $fallback;
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

/**
 * Zero-dependency ZIP writer (stored/uncompressed entries) so exports work
 * even on hosts without the zip extension enabled. Used both for the outer
 * "multiple surveys" bundle and, internally, to package each .xlsx file
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
    public const QUESTION_TITLE = 7;
    public const META = 8;
    public const TABLE_HEADER = 9;
    public const TABLE_HEADER_RIGHT = 10;
    public const DATA_CELL = 11;
    public const DATA_CELL_COUNT = 12;
    public const DATA_CELL_PERCENT = 13;
    public const FOOTER = 14;
}

function xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1">'
        . '<numFmt numFmtId="164" formatCode="0.0&quot;%&quot;"/>'
        . '</numFmts>'
        . '<fonts count="9">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF5470DC"/><sz val="9"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF161A2C"/><sz val="16"/><name val="Calibri"/></font>'
        . '<font><i/><color rgb="FF6B7280"/><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF1F2430"/><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF141826"/><sz val="13"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF141826"/><sz val="11"/><name val="Calibri"/></font>'
        . '<font><i/><color rgb="FF6B7280"/><sz val="9"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FF666D80"/><sz val="9"/><name val="Calibri"/></font>'
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
        . '<cellXfs count="15">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0 default
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 1 kicker
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 2 title
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 3 subtitle
        . '<xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' // 4 label
        . '<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/>' // 5 value
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 6 section heading
        . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 7 question title
        . '<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 8 meta
        . '<xf numFmtId="0" fontId="8" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' // 9 table header
        . '<xf numFmtId="0" fontId="8" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 10 table header right
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' // 11 data cell
        . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 12 data cell count
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 13 data cell percent
        . '<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 14 footer
        . '</cellXfs>'
        . '</styleSheet>';
}

final class XlsxSheetBuilder
{
    private array $rows = [];
    private int $currentRow = 0;
    /** @var list<array{headerRow: int, lastRow: int, columns: list<string>}> */
    private array $tables = [];

    /** @param list<array{value: string|int|float, style?: int, numeric?: bool}> $cells */
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
                if ($cell['numeric'] ?? false) {
                    $num = (float) ($cell['value'] ?? 0);
                    $numStr = rtrim(rtrim(sprintf('%.10F', $num), '0'), '.');
                    if ($numStr === '' || $numStr === '-') $numStr = '0';
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $numStr . '</v></c>';
                } else {
                    $value = (string) ($cell['value'] ?? '');
                    if ($value !== '') {
                        $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . xlsx_escape($value) . '</t></is></c>';
                    } else {
                        $xml .= '<c r="' . $ref . '" s="' . $style . '"/>';
                    }
                }
                $col++;
            }
            $xml .= '</row>';
        }
        return $xml;
    }
}
