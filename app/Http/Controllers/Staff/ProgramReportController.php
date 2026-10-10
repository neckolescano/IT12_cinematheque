<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Report;
use App\Models\ReportEvent;
use App\Services\ProgramReportSheet;
use App\Services\ProgramReports;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Program reports (the Manila report): pick a program → Generate → preview in the Manila format →
 * Submit to Super Admin. A submitted report is locked; admins can request an unlock with a reason,
 * and only the Super Admin unlocks. Export as .xlsx at any stage.
 */
class ProgramReportController extends Controller
{
    public function __construct(private ProgramReports $reports)
    {
    }

    /** Every program with its report state; for the Super Admin, an inbox of submissions and unlock requests. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Report::class);

        // Every program tag with published screenings or a report, with its live totals (one grouped query).
        $overview = $this->reports->overview();
        $programs = Program::with(['report.events'])
            ->where(fn ($q) => $q->whereKey($overview->keys())->orWhereHas('report'))
            ->orderBy('name')->get()
            ->each(fn (Program $p) => $p->setAttribute('totals', $overview->get($p->program_id)));

        $inbox = $request->user()->isSuperAdmin()
            ? ReportEvent::with('report.program', 'report.events', 'user')
                ->whereIn('action', ['submitted', 'resubmitted', 'unlock_requested'])
                ->whereHas('report', fn ($q) => $q->where('status', 'submitted'))
                ->latest('event_id')->get()
                // One entry per report: its latest submission or open unlock request.
                ->unique('report_id')->values()
            : collect();

        return view('staff.program-reports.index', compact('programs', 'inbox'));
    }

    /** One-click export from the list: the locked snapshot once submitted, otherwise live figures. */
    public function exportProgram(Program $program, string $format, ProgramReportSheet $sheet): Response|BinaryFileResponse
    {
        Gate::authorize('viewAny', Report::class);

        $rows = $this->reports->exportRows($program->load('report'));

        if ($format === 'csv') {
            return response($sheet->csv($program, $rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$sheet->filename($program, 'csv').'"',
            ]);
        }

        return response()->download($sheet->buildFor($program, $rows, $sheet->statusFor($program)), $sheet->filename($program), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Generate (or regenerate) a program's report. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Report::class);

        $data = $request->validate(['program_id' => ['required', 'integer', Rule::exists('programs', 'program_id')]], [], ['program_id' => 'program']);
        $program = Program::findOrFail($data['program_id']);
        if ($program->report && Gate::denies('update', $program->report)) {
            return redirect()->route('staff.program-reports.show', $program->report)
                ->withErrors(['report' => 'This report is submitted and locked. Request an unlock to change it.']);
        }

        $report = $this->reports->generate($program, $request->user());

        return redirect()->route('staff.program-reports.show', $report)
            ->with('status', 'Report generated from '.$report->rows()->count().' published screenings. Check it, then submit it to the Super Admin.');
    }

    public function show(Report $report): View
    {
        Gate::authorize('view', $report);

        $report->load('program', 'events.user', 'submittedBy', 'unlockedBy', 'generatedBy');
        $rows = $report->rows()->orderBy('screening_date')->orderBy('start_time')->get();

        return view('staff.program-reports.show', [
            'report' => $report,
            'rows' => $rows,
            'totals' => $this->reports->totals($rows),
            'editable' => Gate::allows('update', $report),
            'pending' => $report->pendingUnlockRequest(),
        ]);
    }

    /** Save the Partner / Type of agency / Notes columns. */
    public function update(Request $request, Report $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        $data = $request->validate([
            'rows' => ['array'],
            'rows.*.partner' => ['nullable', 'string', 'max:150'],
            'rows.*.agency_type' => ['nullable', 'string', 'max:100'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->reports->updateDetails($report->load('rows'), $data['rows'] ?? [], $request->user());

        return back()->with('status', 'Report details saved.');
    }

    public function submit(Request $request, Report $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        $this->reports->submit($report, $request->user());

        return back()->with('status', 'Report submitted to the Super Admin. It is now locked.');
    }

    public function requestUnlock(Request $request, Report $report): RedirectResponse
    {
        Gate::authorize('requestUnlock', $report->load('events'));

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [], ['reason' => 'reason']);
        $this->reports->requestUnlock($report, $request->user(), $data['reason']);

        return back()->with('status', 'Unlock requested. The Super Admin will review it.');
    }

    public function unlock(Request $request, Report $report): RedirectResponse
    {
        Gate::authorize('unlock', $report);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [], ['reason' => 'reason']);
        try {
            $this->reports->unlock($report, $request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return back()->with('status', 'Report unlocked. Admins can edit and resubmit it.');
    }

    public function export(Report $report, ProgramReportSheet $sheet): BinaryFileResponse
    {
        Gate::authorize('view', $report);

        return response()->download($sheet->build($report), $sheet->filename($report->load('program')), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
