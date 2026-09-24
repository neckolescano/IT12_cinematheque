<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payment;
use App\Models\ReservationSeat;
use App\Models\Screening;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reserved vs. attended per screening. No-shows = reserved seats without an attendance row. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Screening::class);

        [$from, $to] = $this->range($request);
        $screenings = $this->screeningRows($from, $to);

        $checkIns = Attendance::with('checkedInBy')
            ->whereBetween('checked_in_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get()
            ->groupBy('checked_in_by')
            ->map(fn ($rows) => ['user' => $rows->first()->checkedInBy, 'count' => $rows->count()]);

        return view('staff.reports.index', compact('screenings', 'checkIns', 'from', 'to'));
    }

    /** Same figures as the on-screen report, as a CSV download (opens in Excel). */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Screening::class);

        [$from, $to] = $this->range($request);
        $rows = $this->screeningRows($from, $to);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₱ and accents correctly
            fputcsv($out, ['Date', 'Start', 'Event', 'Type', 'Seats reserved', 'Checked in', 'No-shows', 'Verified payments (PHP)']);
            foreach ($rows as $row) {
                $s = $row['screening'];
                fputcsv($out, [
                    $s->event_date->format('Y-m-d'),
                    substr($s->start_time, 0, 5),
                    $s->event_title,
                    $s->type,
                    $row['reserved'],
                    $row['attended'],
                    $row['no_shows'] ?? '',
                    number_format((float) $row['verified_amount'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, "cinematheque-davao-report-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        $range = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $range['from'] ?? today()->subDays(30)->toDateString(),
            $range['to'] ?? today()->addDays(30)->toDateString(),
        ];
    }

    private function screeningRows(string $from, string $to): Collection
    {
        return Screening::whereBetween('event_date', [$from, $to])
            ->orderBy('event_date')->orderBy('start_time')
            ->get()
            ->map(function (Screening $screening) {
                $activeSeats = ReservationSeat::where('screening_id', $screening->screening_id)
                    ->whereHas('reservation', fn ($q) => $q->where('status', '!=', 'cancelled'));

                $reserved = (clone $activeSeats)->count();
                $attended = (clone $activeSeats)->whereHas('attendance')->count();

                return [
                    'screening' => $screening,
                    'reserved' => $reserved,
                    'attended' => $attended,
                    'no_shows' => $screening->event_date->lt(today()) ? $reserved - $attended : null,
                    'verified_amount' => Payment::where('status', 'verified')
                        ->whereHas('reservation', fn ($q) => $q->where('screening_id', $screening->screening_id))
                        ->sum('amount'),
                ];
            });
    }
}
