<?php

namespace App\Support;

use RuntimeException;

/**
 * A minimal .xlsx (Office Open XML) writer: one sheet, text and number cells, merged cells, column
 * widths, frozen panes and a fixed set of styles. Enough for the Manila report's two-row header and merged
 * Program / Date cells, without PhpSpreadsheet (which needs PHP's GD extension). The .xlsx package (a zip
 * file) is written here too, with zlib only, so PHP's zip extension is not needed either.
 *
 *   $x = new XlsxWriter('Report');
 *   $x->cell(1, 1, 'PROGRAM', XlsxWriter::HEADER);   // row, column (1-based), value, style
 *   $x->merge(1, 1, 2, 1);                           // A1:A2
 *   $x->save($path);
 */
class XlsxWriter
{
    // Style ids (cellXfs index in styles.xml below).
    public const PLAIN = 0;
    public const TITLE = 1;
    public const HEADER = 2;
    public const TEXT = 3;
    public const CENTER = 4;
    public const NUMBER = 5;
    public const MONEY = 6;
    public const PERCENT = 7;
    public const TOTAL = 8;
    public const TOTAL_NUMBER = 9;
    public const TOTAL_MONEY = 10;
    public const TOTAL_PERCENT = 11;
    public const SUBTITLE = 12;
    public const PROGRAM = 13;
    public const FREE = 14;
    public const PAID = 15;
    // Day bands: the same styles on a light fill, for every other screening day.
    public const BAND_TEXT = 16;
    public const BAND_CENTER = 17;
    public const BAND_NUMBER = 18;
    public const BAND_MONEY = 19;
    public const BAND_PERCENT = 20;

    private const BANDED = [self::TEXT => self::BAND_TEXT, self::CENTER => self::BAND_CENTER, self::NUMBER => self::BAND_NUMBER,
        self::MONEY => self::BAND_MONEY, self::PERCENT => self::BAND_PERCENT];

    /** Fill colour (ARGB) of PROGRAM cells. */
    private string $programFill = 'FF580076';

    /** @var array{0: int, 1: int}|null first scrolling row and column */
    private ?array $freeze = null;

    /** @var array<int, array<int, array{0: string|int|float|null, 1: int}>> */
    private array $cells = [];

    /** @var list<string> */
    private array $merges = [];

    /** @var array<int, float> */
    private array $widths = [];

    /** @var array<int, float> */
    private array $heights = [];

    public function __construct(private string $sheetName = 'Sheet1')
    {
    }

    public function cell(int $row, int $col, string|int|float|null $value, int $style = self::PLAIN): static
    {
        $this->cells[$row][$col] = [$value, $style];

        return $this;
    }

    /** Style a cell without a value (e.g. the borders of a merged area). */
    public function style(int $row, int $col, int $style): static
    {
        $this->cells[$row][$col] = [$this->cells[$row][$col][0] ?? null, $style];

        return $this;
    }

    /** Merge a rectangle; every cell in it takes the top-left cell's style so borders are drawn. */
    public function merge(int $row1, int $col1, int $row2, int $col2): static
    {
        if ($row1 === $row2 && $col1 === $col2) {
            return $this;
        }
        $style = $this->cells[$row1][$col1][1] ?? self::PLAIN;
        for ($r = $row1; $r <= $row2; $r++) {
            for ($c = $col1; $c <= $col2; $c++) {
                if ($r !== $row1 || $c !== $col1) {
                    $this->style($r, $c, $style);
                }
            }
        }
        $this->merges[] = self::ref($row1, $col1).':'.self::ref($row2, $col2);

        return $this;
    }

    /** The banded variant of a body style (unchanged for styles without one). */
    public static function banded(int $style): int
    {
        return self::BANDED[$style] ?? $style;
    }

    public function programFill(string $argb): static
    {
        $this->programFill = $argb;

        return $this;
    }

    /** Keep the rows above $row and the columns before $col in view while scrolling. */
    public function freeze(int $row, int $col): static
    {
        $this->freeze = [$row, $col];

        return $this;
    }

    public function width(int $col, float $width): static
    {
        $this->widths[$col] = $width;

        return $this;
    }

    public function height(int $row, float $points): static
    {
        $this->heights[$row] = $points;

        return $this;
    }

    /** 1 → A, 27 → AA */
    public static function column(int $col): string
    {
        $name = '';
        for (; $col > 0; $col = intdiv($col - 1, 26)) {
            $name = chr(65 + ($col - 1) % 26).$name;
        }

        return $name;
    }

    public static function ref(int $row, int $col): string
    {
        return self::column($col).$row;
    }

    public function save(string $path): void
    {
        $zip = self::zip([
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRels(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->sheet(),
        ]);
        if (file_put_contents($path, $zip) === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
    }

    /**
     * A zip archive (deflate) built in memory: local headers and data, then the central directory.
     *
     * @param  array<string, string>  $files  path inside the archive => contents
     */
    private static function zip(array $files): string
    {
        $now = getdate();
        $time = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
        $date = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];
        $body = $central = '';
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $packed = gzdeflate($content, 6);
            $offset = strlen($body);
            $body .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 8, $time, $date, $crc, strlen($packed), strlen($content), strlen($name), 0).$name.$packed;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 8, $time, $date, $crc, strlen($packed), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }

        return $body.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, count($files), count($files), strlen($central), strlen($body), 0);
    }

    private function sheet(): string
    {
        ksort($this->cells);
        $rows = '';
        foreach ($this->cells as $r => $cols) {
            ksort($cols);
            $height = isset($this->heights[$r]) ? ' ht="'.$this->heights[$r].'" customHeight="1"' : '';
            $rows .= '<row r="'.$r.'"'.$height.'>';
            foreach ($cols as $c => [$value, $style]) {
                $ref = self::ref($r, $c);
                $s = $style ? ' s="'.$style.'"' : '';
                $rows .= match (true) {
                    $value === null || $value === '' => '<c r="'.$ref.'"'.$s.'/>',
                    is_int($value) || is_float($value) => '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>',
                    default => '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) $value).'</t></is></c>',
                };
            }
            $rows .= '</row>';
        }

        $cols = '';
        if ($this->widths) {
            ksort($this->widths);
            $cols = '<cols>';
            foreach ($this->widths as $c => $w) {
                $cols .= '<col min="'.$c.'" max="'.$c.'" width="'.$w.'" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $merges = $this->merges
            ? '<mergeCells count="'.count($this->merges).'">'.implode('', array_map(fn ($m) => '<mergeCell ref="'.$m.'"/>', $this->merges)).'</mergeCells>'
            : '';

        $pane = '';
        if ($this->freeze) {
            [$fr, $fc] = $this->freeze;
            $pane = '<pane'.($fc > 1 ? ' xSplit="'.($fc - 1).'"' : '').($fr > 1 ? ' ySplit="'.($fr - 1).'"' : '')
                .' topLeftCell="'.self::ref($fr, $fc).'" activePane="bottomRight" state="frozen"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0" zoomScale="90">'.$pane.'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .$cols.'<sheetData>'.$rows.'</sheetData>'.$merges
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function styles(): string
    {
        // Fonts: 0 normal, 1 bold, 2 bold 14pt, 3 italic grey, 4 bold white
        // Fills: 0 none, 1 gray125 (required), 2 brand gold, 3 light grey, 4 day band (cream), 5 program colour,
        //        6 free (light green), 7 paid (light blue)
        // Borders: 0 none, 1 thin all round
        // numFmts: 164 = #,##0.00 ; 165 = 0.00"%"
        $xf = fn (int $font, int $fill, int $border, int $numFmt, string $h, bool $wrap = false) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0"'
            .($font ? ' applyFont="1"' : '').($fill ? ' applyFill="1"' : '').($border ? ' applyBorder="1"' : '').($numFmt ? ' applyNumberFormat="1"' : '')
            .' applyAlignment="1"><alignment horizontal="'.$h.'" vertical="center"'.($wrap ? ' wrapText="1"' : '').'/></xf>';

        $solid = fn (string $argb) => '<fill><patternFill patternType="solid"><fgColor rgb="'.$argb.'"/><bgColor indexed="64"/></patternFill></fill>';

        $xfs = [
            self::PLAIN => $xf(0, 0, 0, 0, 'general'),
            self::TITLE => $xf(2, 0, 0, 0, 'left'),
            self::HEADER => $xf(1, 2, 1, 0, 'center', true),
            self::TEXT => $xf(0, 0, 1, 0, 'left', true),
            self::CENTER => $xf(0, 0, 1, 0, 'center', true),
            self::NUMBER => $xf(0, 0, 1, 1, 'center'),
            self::MONEY => $xf(0, 0, 1, 164, 'right'),
            self::PERCENT => $xf(0, 0, 1, 165, 'center'),
            self::TOTAL => $xf(1, 3, 1, 0, 'left', true),
            self::TOTAL_NUMBER => $xf(1, 3, 1, 1, 'center'),
            self::TOTAL_MONEY => $xf(1, 3, 1, 164, 'right'),
            self::TOTAL_PERCENT => $xf(1, 3, 1, 165, 'center'),
            self::SUBTITLE => $xf(3, 0, 0, 0, 'left'),
            self::PROGRAM => $xf(4, 5, 1, 0, 'center', true),
            self::FREE => $xf(1, 6, 1, 0, 'center'),
            self::PAID => $xf(1, 7, 1, 0, 'center'),
            self::BAND_TEXT => $xf(0, 4, 1, 0, 'left', true),
            self::BAND_CENTER => $xf(0, 4, 1, 0, 'center', true),
            self::BAND_NUMBER => $xf(0, 4, 1, 1, 'center'),
            self::BAND_MONEY => $xf(0, 4, 1, 164, 'right'),
            self::BAND_PERCENT => $xf(0, 4, 1, 165, 'center'),
        ];
        ksort($xfs);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="0.00&quot;%&quot;"/></numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="14"/><name val="Arial"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Arial"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            .'</fonts>'
            .'<fills count="8"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .$solid('FFEBBC00').$solid('FFEFEFEF').$solid('FFFFF8E1').$solid($this->programFill).$solid('FFE3F4E8').$solid('FFE3ECFB').'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($xfs).'">'.implode('', $xfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        // Sheet names: max 31 characters, none of []:*?/\
        $name = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $this->sheetName), 0, 31);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::xml($name).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private static function xml(string $text): string
    {
        // Strip control characters XML 1.0 can't hold, then escape.
        return htmlspecialchars(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
