<?php

namespace App\Services;

/**
 * Minimal multi-sheet XLSX reader and writer.
 *
 * The catalogue import/export needs more than one sheet — versions and options
 * are different grains and do not belong on the same grid — and the readers
 * already in the app take sheet 1 and nothing else. Rather than add a
 * spreadsheet library for two jobs, this generalises what CatalogueController
 * and the importers were already doing by hand: ZipArchive + DOMDocument to
 * read, hand-built XML to write.
 *
 * Deliberately small. It understands shared strings, inline strings and plain
 * numbers, which is what Excel, LibreOffice and Google Sheets produce for a
 * flat grid of text and numbers. It does not do formulas, dates or styling
 * beyond a header row, and it does not need to.
 */
class Xlsx
{
    /**
     * Read every sheet of a workbook.
     *
     * @return array<string, array<int, array<int, string>>> sheet name => rows => cells
     */
    public static function read(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = self::sharedStrings($zip);

        // Sheet name → part path, via the workbook and its relationships. The
        // order of xl/worksheets/sheetN.xml on disk is not the tab order and
        // the numbering is not dense, so both files are needed to be sure
        // which grid is called what.
        $rels = [];
        if (($xml = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $doc = new \DOMDocument();
            $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($doc->getElementsByTagName('Relationship') as $rel) {
                $rels[$rel->getAttribute('Id')] = ltrim($rel->getAttribute('Target'), '/');
            }
        }

        $sheets = [];
        if (($xml = $zip->getFromName('xl/workbook.xml')) !== false) {
            $doc = new \DOMDocument();
            $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($doc->getElementsByTagName('sheet') as $i => $sheet) {
                $rid    = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                $target = $rels[$rid] ?? ('worksheets/sheet' . ($i + 1) . '.xml');
                $sheets[$sheet->getAttribute('name')] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
        if (! $sheets) {
            $sheets = ['Sheet1' => 'xl/worksheets/sheet1.xml'];
        }

        $out = [];
        foreach ($sheets as $name => $part) {
            $xml = $zip->getFromName($part);
            $out[$name] = $xml === false ? [] : self::rows($xml, $shared);
        }
        $zip->close();

        return $out;
    }

    /**
     * Read one sheet, chosen by name. Names are matched case-insensitively and
     * accent-blind, so "Bateaux", "BATEAUX" and a hand-renamed "bateaux" all
     * find the same grid. Falls back to the first sheet when nothing matches,
     * which is what a dealer who saved a single-sheet CSV as XLSX will have.
     *
     * @param  array<int, string>  $names  candidates, best first
     * @return array<int, array<int, string>>
     */
    public static function sheet(array $allSheets, array $names): array
    {
        // Str::ascii handles the whole Latin range, so "Bâteaux" and "BATEAUX"
        // are the same sheet however the dealer spelled the tab.
        $key = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower(\Illuminate\Support\Str::ascii($s)));

        $byKey = [];
        foreach ($allSheets as $name => $rows) {
            $byKey[$key($name)] = $rows;
        }
        foreach ($names as $wanted) {
            if (isset($byKey[$key($wanted)])) {
                return $byKey[$key($wanted)];
            }
        }

        return [];
    }

    /**
     * Write a workbook.
     *
     * @param  array<string, array{headers: array<int, string>, rows: array<int, array<int, mixed>>, widths?: array<int, int>}>  $sheets
     * @return string  path to a temp file the caller owns
     */
    public static function write(array $sheets, string $prefix = 'nautiqs-'): string
    {
        // One shared-string table across the whole workbook, as Excel expects.
        $strings = []; $sMap = [];
        $idx = function (string $s) use (&$strings, &$sMap): int {
            if (isset($sMap[$s])) return $sMap[$s];
            $sMap[$s] = count($strings);
            $strings[] = $s;
            return $sMap[$s];
        };

        $sheetXmls = [];
        $sstCount  = 0;
        $n         = 0;

        foreach ($sheets as $name => $sheet) {
            $n++;
            $headers = $sheet['headers'] ?? [];
            $rows    = $sheet['rows'] ?? [];
            $widths  = $sheet['widths'] ?? [];

            $rowsXml = '<row r="1">';
            foreach ($headers as $c => $h) {
                $rowsXml .= '<c r="' . self::col($c) . '1" t="s" s="1"><v>' . $idx((string) $h) . '</v></c>';
            }
            $rowsXml .= '</row>';

            foreach ($rows as $r => $row) {
                $rowNum  = $r + 2;
                $rowsXml .= '<row r="' . $rowNum . '">';
                foreach (array_values($row) as $c => $val) {
                    if ($val === '' || $val === null) continue;
                    $ref = self::col($c) . $rowNum;
                    if (is_int($val) || is_float($val)) {
                        $rowsXml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
                    } else {
                        $rowsXml .= '<c r="' . $ref . '" t="s"><v>' . $idx((string) $val) . '</v></c>';
                    }
                }
                $rowsXml .= '</row>';
            }

            foreach (array_merge([$headers], $rows) as $row) {
                foreach ($row as $v) if (is_string($v) && $v !== '') $sstCount++;
            }

            $colsXml = '';
            if ($widths) {
                $colsXml = '<cols>';
                foreach ($widths as $i => $w) {
                    $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
                }
                $colsXml .= '</cols>';
            }

            $sheetXmls[$n] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetViews><sheetView' . ($n === 1 ? ' tabSelected="1"' : '') . ' workbookViewId="0">'
                . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                . $colsXml
                . '<sheetData>' . $rowsXml . '</sheetData>'
                . '</worksheet>';
        }

        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $sstCount . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $sstXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        $sstXml .= '</sst>';

        // Sheet relationship ids start above the two fixed parts so the
        // shared-string and style targets keep stable ids whatever the count.
        $sheetTags = ''; $relTags = ''; $overrides = ''; $i = 0;
        foreach (array_keys($sheets) as $name) {
            $i++;
            $sheetTags .= '<sheet name="' . htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '" sheetId="' . $i . '" r:id="rId' . ($i + 10) . '"/>';
            $relTags   .= '<Relationship Id="rId' . ($i + 10) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . $i . '.xml"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        $path = tempnam(sys_get_temp_dir(), $prefix) . '.xlsx';
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetTags . '</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relTags
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/sharedStrings.xml', $sstXml);
        $zip->addFromString('xl/styles.xml', self::STYLES);
        foreach ($sheetXmls as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $xml);
        }
        $zip->close();

        return $path;
    }

    /* ------------------------------------------------------------ internals */

    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF0E4F79"/></patternFill></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'
        . '<alignment horizontal="left" vertical="center"/></xf></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    /** @return array<int, string> */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $out = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) === false) {
            return $out;
        }
        $doc = new \DOMDocument();
        $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($doc->getElementsByTagName('si') as $si) {
            $text = '';
            // A single <si> can be split into several <t> runs when part of the
            // cell was styled differently; they concatenate into one value.
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->nodeValue;
            }
            $out[] = $text;
        }
        return $out;
    }

    /** @return array<int, array<int, string>> */
    private static function rows(string $sheetXml, array $shared): array
    {
        $doc = new \DOMDocument();
        $doc->loadXML($sheetXml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $rows = [];
        foreach ($doc->getElementsByTagName('row') as $rowEl) {
            $rowData = []; $maxCol = 0;
            foreach ($rowEl->getElementsByTagName('c') as $cell) {
                $col  = self::colIndex(preg_replace('/\d+/', '', $cell->getAttribute('r')));
                $type = $cell->getAttribute('t');
                $vEl  = $cell->getElementsByTagName('v')->item(0);
                $val  = $vEl ? $vEl->nodeValue : '';

                if ($type === 's') {
                    $val = $shared[(int) $val] ?? '';
                } elseif ($type === 'inlineStr') {
                    $tEl = $cell->getElementsByTagName('t')->item(0);
                    $val = $tEl ? $tEl->nodeValue : '';
                }

                $rowData[$col] = (string) $val;
                if ($col > $maxCol) $maxCol = $col;
            }
            $padded = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $padded[] = $rowData[$c] ?? '';
            }
            $rows[] = $padded;
        }
        return $rows;
    }

    private static function col(int $i): string
    {
        $i++; $s = '';
        while ($i > 0) { $r = ($i - 1) % 26; $s = chr(65 + $r) . $s; $i = intdiv($i - 1, 26); }
        return $s;
    }

    private static function colIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }
}
