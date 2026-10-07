<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ReservationSeat;
use App\Models\Screening;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Two reports over one date range:
 *  - attendance:   reserved vs. attended per screening (no-shows = reserved seats with no attendance row)
 *  - demographics: everyone actually admitted, with the logsheet details they declared
 *                  (name, age, sex, company/school, contact, email, senior ID, PWD). Reservations
 *                  without an admission, no-shows and cancelled bookings are left out.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Screening::class);

        [$from, $to] = $this->range($request);
        $view = $request->query('view') === 'demographics' ? 'demographics' : 'attendance';

        if ($view === 'demographics') {
            return view('staff.reports.index', [
                'view' => $view, 'from' => $from, 'to' => $to,
                'admitted' => $this->admitted($from, $to)->paginate(50)->withQueryString(),
                'summary' => $this->demographicSummary($from, $to),
            ]);
        }

        $screenings = $this->screeningRows($from, $to);

        return view('staff.reports.index', compact('view', 'screenings', 'from', 'to'));
    }

    /** Same figures as the on-screen report, as a CSV download (opens in Excel). */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Screening::class);

        [$from, $to] = $this->range($request);

        if ($request->query('view') === 'demographics') {
            return $this->exportDemographics($from, $to);
        }

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

    /** The demographic list as CSV: one row per admitted moviegoer, logsheet columns. */
    private function exportDemographics(string $from, string $to): StreamedResponse
    {
        $query = $this->admitted($from, $to);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Event', 'Last name', 'First name', 'Middle name', 'Age', 'Sex', 'Company/School', 'Contact no.', 'Email', 'Senior citizen ID', 'PWD']);
            $query->chunk(500, function ($seats) use ($out) {
                foreach ($seats as $rs) {
                    $a = $rs->attendee;
                    fputcsv($out, [
                        $rs->screening->event_date->format('Y-m-d'),
                        $rs->screening->event_title,
                        $a?->last_name, $a?->first_name, $a?->middle_name,
                        $a?->age, $a?->sex, $a?->company_school, $a?->contact_no, $a?->email,
                        $a?->senior_card_no,
                        $a?->pwd_id_no ?: ($a?->pwd_indicator ? 'Yes' : ''),
                    ]);
                }
            });
            fclose($out);
        }, "cinematheque-davao-demographics-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Seats actually admitted (an attendance row) in screenings within the range, not cancelled. */
    private function admitted(string $from, string $to): Builder
    {
        return ReservationSeat::query()
            ->select('reservation_seats.*')
            ->with('attendee', 'screening', 'seat')
            ->join('screenings', 'screenings.screening_id', '=', 'reservation_seats.screening_id')
            ->whereBetween('screenings.event_date', [$from, $to])
            ->whereHas('attendance')
            ->whereHas('reservation', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->orderBy('screenings.event_date')->orderBy('screenings.start_time')->orderBy('reservation_seats.seat_id');
    }

    /** Totals over the same people: admitted, by sex, senior citizens, PWD. */
    private function demographicSummary(string $from, string $to): array
    {
        $people = $this->admitted($from, $to)->get()->pluck('attendee')->filter();

        return [
            'total' => $people->count(),
            'male' => $people->where('sex', 'M')->count(),
            'female' => $people->where('sex', 'F')->count(),
            'senior' => $people->filter(fn ($a) => filled($a->senior_card_no))->count(),
            'pwd' => $people->filter(fn ($a) => $a->pwd_indicator || filled($a->pwd_id_no))->count(),
        ];
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
