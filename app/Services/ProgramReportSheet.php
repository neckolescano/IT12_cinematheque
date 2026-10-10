<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Report;
use App\Support\XlsxWriter as X;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The program report as an .xlsx in the Manila unified layout: the Free and Paid Screening Report sheets
 * merged into one table, with the sheets' two-row header (NO. OF VIEWERS over M / F, TICKETS SOLD over
 * REGULAR / DISCOUNT), the Program cell merged down every row and Date / Day merged per day, then the
 * program total row. Ticket and sales cells are filled for paid rows only.
 */
class ProgramReportSheet
{
    /** [heading, sub-heading or null, width] in column order. */
    public const COLUMNS = [
        ['PROGRAM', null, 26],
        ['DATE', null, 14],
        ['DAY', null, 7],
        ['TIME', null, 10],
        ['TITLE OF FILM', null, 32],
        ['ADMISSION', null, 11],
        ['NO. OF FILMS', null, 9],
        ['NO. OF VIEWERS', 'M', 7],
        ['NO. OF VIEWERS', 'F', 7],
        ['PWD', null, 7],
        ['SENIOR', null, 8],
        ['TOTAL NO. OF AUDIENCE', null, 11],
        ['OCCUPANCY RATE', null, 11],
        ['TICKETS SOLD', 'REGULAR', 10],
        ['TICKETS SOLD', 'DISCOUNT', 10],
        ['TOTAL SALES', null, 13],
        ['PARTNER', null, 22],
        ['TYPE OF AGENCY', null, 16],
        ['NOTES', null, 28],
    ];

    public function __construct(private ProgramReports $reports)
    {
    }

    /** Write a generated report to a temporary .xlsx and return its path. */
    public function build(Report $report): string
    {
        $report->loadMissing('program', 'rows');

        return $this->buildFor($report->program, $report->rows, $this->statusLine($report));
    }

    /** Write any set of report rows (a snapshot or live figures) for a program to a temporary .xlsx. */
    public function buildFor(Program $program, Collection $rows, string $status): string
    {
        $rows = $rows->sortBy(fn ($r) => $r->screening_date->toDateString().' '.$r->start_time)->values();
        $x = new X(mb_substr($program->name, 0, 31));

        // Title block
        $x->cell(1, 1, 'CINEMATHEQUE CENTRE DAVAO — PROGRAM REPORT', X::TITLE)->height(1, 22);
        $x->cell(2, 1, $program->name.' · '.$status, X::SUBTITLE);

        // Two-row header (rows 4–5): single headings span both rows, grouped ones span their columns.
        $h = 4;
        foreach (self::COLUMNS as $i => [$heading, $sub, $width]) {
            $col = $i + 1;
            $x->width($col, $width);
            if ($sub === null) {
                $x->cell($h, $col, $heading, X::HEADER)->merge($h, $col, $h + 1, $col);
            } else {
                if ((self::COLUMNS[$i - 1][0] ?? null) !== $heading) {
                    $span = count(array_filter(self::COLUMNS, fn ($c) => $c[0] === $heading));
                    $x->cell($h, $col, $heading, X::HEADER)->merge($h, $col, $h, $col + $span - 1);
                }
                $x->cell($h + 1, $col, $sub, X::HEADER);
            }
        }
        $x->height($h, 30)->height($h + 1, 18);

        // Rows
        $first = $h + 2;
        $r = $first;
        foreach ($rows as $row) {
            $paid = $row->type === 'paid';
            $values = [
                [$program->name, X::CENTER],
                [$row->screening_date->format('M j, Y'), X::CENTER],
                [$row->screening_date->format('D'), X::CENTER],
                [Carbon::parse($row->start_time)->format('g:i A'), X::CENTER],
                [$row->film_title, X::TEXT],
                [$paid ? 'Paid' : 'Free', X::CENTER],
                [(int) $row->films_count, X::NUMBER],
                [(int) $row->male, X::NUMBER],
                [(int) $row->female, X::NUMBER],
                [(int) $row->pwd, X::NUMBER],
                [(int) $row->senior, X::NUMBER],
                [(int) $row->total_audience, X::NUMBER],
                [(float) $row->occupancy_rate, X::PERCENT],
                [$paid ? (int) $row->regular_count : null, X::NUMBER],
                [$paid ? (int) $row->discount_count : null, X::NUMBER],
                [$paid ? (float) $row->total_sales : null, X::MONEY],
                [$row->partner, X::TEXT],
                [$row->agency_type, X::TEXT],
                [$row->notes, X::TEXT],
            ];
            foreach ($values as $i => [$value, $style]) {
                $x->cell($r, $i + 1, $value, $style);
            }
            $r++;
        }
        $last = $r - 1;

        // Program merged down its rows; Date and Day merged per day.
        if ($rows->isNotEmpty()) {
            $x->merge($first, 1, $last, 1);
            $start = $first;
            foreach ($rows->values() as $i => $row) {
                $next = $rows->get($i + 1);
                if (! $next || ! $next->screening_date->isSameDay($row->screening_date)) {
                    $x->merge($start, 2, $first + $i, 2)->merge($start, 3, $first + $i, 3);
                    $start = $first + $i + 1;
                }
            }
        }

        // Program total
        $t = $this->reports->totals($rows);
        $total = [
            ['PROGRAM TOTAL', X::TOTAL], [$t['screenings'].' screenings', X::TOTAL], [null, X::TOTAL], [null, X::TOTAL], [null, X::TOTAL], [null, X::TOTAL],
            [$t['films'], X::TOTAL_NUMBER], [$t['male'], X::TOTAL_NUMBER], [$t['female'], X::TOTAL_NUMBER],
            [$t['pwd'], X::TOTAL_NUMBER], [$t['senior'], X::TOTAL_NUMBER], [$t['total_audience'], X::TOTAL_NUMBER],
            [$t['occupancy_rate'], X::TOTAL_PERCENT], [$t['regular_count'], X::TOTAL_NUMBER], [$t['discount_count'], X::TOTAL_NUMBER],
            [$t['total_sales'], X::TOTAL_MONEY], [null, X::TOTAL], [null, X::TOTAL], [null, X::TOTAL],
        ];
        foreach ($total as $i => [$value, $style]) {
            $x->cell($r, $i + 1, $value, $style);
        }
        $x->merge($r, 2, $r, 6);
        $x->cell($r + 2, 1, 'Occupancy rate = total audience ÷ hall capacity × 100. The total row shows the average occupancy. Total sales count payments verified by PayMongo.', X::SUBTITLE);

        $path = tempnam(sys_get_temp_dir(), 'ccd-report');
        $x->save($path);

        return $path;
    }

    /** The same table as CSV (one row per screening plus the program total; no merged cells), UTF-8 with a BOM for Excel. */
    public function csv(Program $program, Collection $rows): string
    {
        $rows = $rows->sortBy(fn ($r) => $r->screening_date->toDateString().' '.$r->start_time)->values();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Program', 'Date', 'Day', 'Time', 'Title of film', 'Admission', 'No. of films', 'M', 'F', 'PWD', 'Senior',
            'Total audience', 'Occupancy rate (%)', 'Regular', 'Discount', 'Total sales', 'Partner', 'Type of agency', 'Notes']);
        foreach ($rows as $r) {
            $paid = $r->type === 'paid';
            fputcsv($out, [
                $program->name, $r->screening_date->format('M j, Y'), $r->screening_date->format('D'), Carbon::parse($r->start_time)->format('g:i A'),
                $r->film_title, $paid ? 'Paid' : 'Free', $r->films_count, $r->male, $r->female, $r->pwd, $r->senior,
                $r->total_audience, number_format((float) $r->occupancy_rate, 2, '.', ''),
                $paid ? $r->regular_count : '', $paid ? $r->discount_count : '', $paid ? number_format((float) $r->total_sales, 2, '.', '') : '',
                $r->partner, $r->agency_type, $r->notes,
            ]);
        }
        $t = $this->reports->totals($rows);
        fputcsv($out, ['PROGRAM TOTAL', $t['screenings'].' screenings', '', '', '', '', $t['films'], $t['male'], $t['female'], $t['pwd'], $t['senior'],
            $t['total_audience'], number_format($t['occupancy_rate'], 2, '.', ''), $t['regular_count'], $t['discount_count'], number_format($t['total_sales'], 2, '.', ''), '', '', '']);
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function filename(Report|Program $of, string $extension = 'xlsx'): string
    {
        $program = $of instanceof Report ? $of->program : $of;

        return 'program-report-'.\Illuminate\Support\Str::slug($program->name).'.'.$extension;
    }

    /** The subtitle under the title: snapshot status, or that the figures are live. */
    public function statusFor(Program $program): string
    {
        return $program->report?->isLocked() ? $this->statusLine($program->report) : 'Live figures as of '.now()->format('M j, Y g:i A').' (not submitted)';
    }

    private function statusLine(Report $report): string
    {
        return match ($report->status) {
            'submitted' => 'Submitted '.$report->submitted_at?->format('M j, Y g:i A'),
            'unlocked' => 'Unlocked for changes',
            default => 'Draft',
        }.' · Generated '.$report->updated_at?->format('M j, Y');
    }
}
