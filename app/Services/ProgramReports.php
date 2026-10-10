<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Report;
use App\Models\ReportEvent;
use App\Models\ReportRow;
use App\Models\ReservationSeat;
use App\Models\Screening;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Manila report of a program: one frozen row per published screening, free and paid together.
 *
 *   viewers        people admitted at the door (attendance), by sex; PWD / Senior from their IDs
 *                  (someone with both IDs counts in both, but got one discount)
 *   occupancy      total audience ÷ hall capacity × 100, two decimals (26 of 120 = 21.67)
 *   tickets/sales  paid rows only: confirmed, held seats by discount type; sales = amount_due of
 *                  seats whose payment PayMongo verified
 *
 * Workflow: generate (draft) → submit (locked for admins) → request unlock (with a reason) → the Super
 * Admin unlocks (with a reason) → edit / regenerate → submit again. Every step is a report_event.
 */
class ProgramReports
{
    /** Create or rebuild the program's report. Partner / agency / notes typed on the report are kept. */
    public function generate(Program $program, User $by): Report
    {
        return DB::transaction(function () use ($program, $by) {
            $report = Report::where('program_id', $program->program_id)->lockForUpdate()->first();
            if ($report?->isLocked()) {
                throw new DomainException('This report is submitted and locked. Ask the Super Admin to unlock it first.');
            }

            $report ??= Report::create(['program_id' => $program->program_id, 'generated_by' => $by->user_id]);
            $report->update(['generated_by' => $by->user_id]);

            $kept = $report->rows()->whereNotNull('screening_id')->get()->keyBy('screening_id');
            $report->rows()->delete();

            $screenings = $program->screenings()->with('movie')
                ->where('status', 'published')
                ->orderBy('event_date')->orderBy('start_time')
                ->get();

            foreach ($screenings as $screening) {
                $report->rows()->create($this->rowFor($screening, $kept->get($screening->screening_id)));
            }

            $this->log($report, $by, 'generated');

            return $report->refresh();
        });
    }

    /** The frozen line for one screening. */
    public function rowFor(Screening $screening, ?ReportRow $previous = null): array
    {
        $admitted = ReservationSeat::where('screening_id', $screening->screening_id)
            ->whereHas('attendance')
            ->with('attendee')
            ->get()
            ->pluck('attendee')->filter();

        $male = $admitted->where('sex', 'M')->count();
        $female = $admitted->where('sex', 'F')->count();
        $audience = $male + $female;

        $tickets = $screening->isPaid()
            ? ReservationSeat::where('screening_id', $screening->screening_id)->held()
                ->whereHas('reservation', fn ($r) => $r->where('status', 'confirmed'))
                ->with('reservation.payment')
                ->get()
            : collect();
        $paid = $tickets->filter(fn ($seat) => $seat->reservation->payment?->isPaid());

        return [
            'screening_id' => $screening->screening_id,
            'type' => $screening->type,
            'screening_date' => $screening->event_date->toDateString(),
            'start_time' => $screening->start_time,
            'film_title' => mb_substr($screening->movie?->title ?? $screening->event_title, 0, 150),
            'films_count' => $screening->films_count ?? 1,
            'male' => $male,
            'female' => $female,
            'pwd' => $admitted->filter(fn ($a) => filled($a->pwd_id_no))->count(),
            'senior' => $admitted->filter(fn ($a) => filled($a->senior_card_no))->count(),
            'total_audience' => $audience,
            'occupancy_rate' => $screening->total_seats ? round($audience / $screening->total_seats * 100, 2) : 0,
            'regular_count' => $tickets->where('discount_type', 'none')->count(),
            'discount_count' => $tickets->where('discount_type', '!=', 'none')->count(),
            'total_sales' => round($paid->sum(fn ($seat) => (float) $seat->amount_due), 2),
            // Typed on the report wins over the screening's own values.
            'partner' => $previous?->partner ?? $screening->partner,
            'agency_type' => $previous?->agency_type ?? $screening->agency_type,
            'notes' => $previous?->notes ?? $screening->notes,
        ];
    }

    /**
     * Live totals per program tag, for the Program reports list: one query, grouped by program. Viewers are
     * admitted attendees; revenue is the amount due of held tickets in confirmed bookings PayMongo verified
     * (the same rules as rowFor()). Keyed by program_id.
     */
    public function overview(): Collection
    {
        $perScreening = DB::table('reservation_seats as rs')
            ->join('reservations as r', 'r.reservation_id', '=', 'rs.reservation_id')
            ->join('reservation_attendees as ra', 'ra.reservation_seat_id', '=', 'rs.reservation_seat_id')
            ->leftJoin('attendances as att', 'att.reservation_seat_id', '=', 'rs.reservation_seat_id')
            ->leftJoin('payments as pay', 'pay.reservation_id', '=', 'r.reservation_id')
            ->groupBy('rs.screening_id')
            ->selectRaw("rs.screening_id,
                SUM(att.attendance_id IS NOT NULL AND ra.sex = 'M') AS male,
                SUM(att.attendance_id IS NOT NULL AND ra.sex = 'F') AS female,
                SUM(att.attendance_id IS NOT NULL AND ra.pwd_id_no IS NOT NULL) AS pwd,
                SUM(att.attendance_id IS NOT NULL AND ra.senior_card_no IS NOT NULL) AS senior,
                SUM(CASE WHEN r.status = 'confirmed' AND pay.status = 'verified' AND rs.released_at IS NULL THEN rs.amount_due ELSE 0 END) AS sales");

        return DB::table('programs as p')
            ->join('screenings as s', fn ($j) => $j->on('s.program_id', '=', 'p.program_id')->where('s.status', 'published'))
            ->leftJoinSub($perScreening, 't', 't.screening_id', '=', 's.screening_id')
            ->groupBy('p.program_id', 'p.name')
            ->orderBy('p.name')
            ->selectRaw("p.program_id, p.name,
                COUNT(*) AS screenings,
                SUM(s.type = 'free') AS free_screenings,
                SUM(s.type = 'paid') AS paid_screenings,
                COUNT(DISTINCT s.movie_id) AS films,
                COALESCE(SUM(t.male), 0) AS male,
                COALESCE(SUM(t.female), 0) AS female,
                COALESCE(SUM(t.pwd), 0) AS pwd,
                COALESCE(SUM(t.senior), 0) AS senior,
                COALESCE(SUM(t.sales), 0) AS revenue")
            ->get()
            ->keyBy('program_id');
    }

    /**
     * The rows a one-click export uses: the locked snapshot once the report is submitted, otherwise live
     * figures (built like a regenerate, not saved; typed Partner / Agency / Notes are kept).
     */
    public function exportRows(Program $program): Collection
    {
        $report = $program->report;
        if ($report?->isLocked()) {
            return $report->rows()->orderBy('screening_date')->orderBy('start_time')->get();
        }

        $kept = $report ? $report->rows()->whereNotNull('screening_id')->get()->keyBy('screening_id') : collect();

        return $program->screenings()->with('movie')->where('status', 'published')
            ->orderBy('event_date')->orderBy('start_time')->get()
            ->map(fn (Screening $s) => new ReportRow($this->rowFor($s, $kept->get($s->screening_id))))
            ->values();
    }

    /** The program total row, as at the foot of the Manila sheets. */
    public function totals(Collection $rows): array
    {
        return [
            'screenings' => $rows->count(),
            'films' => $rows->sum('films_count'),
            'male' => $rows->sum('male'),
            'female' => $rows->sum('female'),
            'pwd' => $rows->sum('pwd'),
            'senior' => $rows->sum('senior'),
            'total_audience' => $rows->sum('total_audience'),
            'occupancy_rate' => $rows->isEmpty() ? 0 : round($rows->avg(fn ($r) => (float) $r->occupancy_rate), 2),
            'regular_count' => $rows->sum('regular_count'),
            'discount_count' => $rows->sum('discount_count'),
            'total_sales' => round($rows->sum(fn ($r) => (float) $r->total_sales), 2),
        ];
    }

    /** Save the Partner / Type of agency / Notes columns typed on the report. */
    public function updateDetails(Report $report, array $details, User $by): void
    {
        $this->assertEditable($report);

        DB::transaction(function () use ($report, $details, $by) {
            foreach ($report->rows as $row) {
                if (isset($details[$row->report_row_id])) {
                    $row->update(collect($details[$row->report_row_id])->only(['partner', 'agency_type', 'notes'])
                        ->map(fn ($v) => filled($v) ? trim($v) : null)->all());
                }
            }
            $this->log($report, $by, 'edited');
        });
    }

    public function submit(Report $report, User $by): void
    {
        $this->assertEditable($report);

        $resubmit = $report->status === 'unlocked';
        $report->update(['status' => 'submitted', 'submitted_by' => $by->user_id, 'submitted_at' => now()]);
        $this->log($report, $by, $resubmit ? 'resubmitted' : 'submitted');
    }

    public function requestUnlock(Report $report, User $by, string $reason): void
    {
        if (! $report->isLocked()) {
            throw new DomainException('Only a submitted report can be unlocked.');
        }
        $this->log($report, $by, 'unlock_requested', $reason);
    }

    public function unlock(Report $report, User $by, string $reason): void
    {
        if (! $by->isSuperAdmin()) {
            throw new DomainException('Only the Super Admin can unlock a report.');
        }
        if (! $report->isLocked()) {
            throw new DomainException('This report is not locked.');
        }
        $report->update(['status' => 'unlocked', 'unlocked_by' => $by->user_id, 'unlocked_at' => now()]);
        $this->log($report, $by, 'unlocked', $reason);
    }

    private function assertEditable(Report $report): void
    {
        if ($report->isLocked()) {
            throw new DomainException('This report is submitted and locked. Ask the Super Admin to unlock it first.');
        }
    }

    private function log(Report $report, User $by, string $action, ?string $reason = null): void
    {
        ReportEvent::create(['report_id' => $report->report_id, 'user_id' => $by->user_id, 'action' => $action, 'reason' => $reason]);
    }
}
