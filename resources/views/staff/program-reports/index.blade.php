@extends('layouts.staff')

@section('title', 'Program reports')

{{-- One Manila report per program. Pick a program → Generate → check it → Submit to the Super Admin.
     The Super Admin also sees an inbox: submitted reports and unlock requests, newest first. --}}
@section('content')
    <header class="page-head">
        <div>
            <h1>Program reports</h1>
        </div>
        @can('create', App\Models\Report::class)
            <form class="report-generate" method="POST" action="{{ route('staff.program-reports.store') }}">
                @csrf
                <label class="sr-only" for="program_id">Program</label>
                <select id="program_id" name="program_id" required>
                    <option value="">Choose a program</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->program_id }}">{{ $program->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn--primary">Generate</button>
            </form>
        @endcan
    </header>

    @if (auth()->user()->isSuperAdmin())
        <section class="panel report-inbox" aria-labelledby="inbox-title">
            <h2 class="panel__title" id="inbox-title">Inbox <span class="muted small">{{ $inbox->count() }} waiting</span></h2>
            @forelse ($inbox as $event)
                <a class="inbox-item" href="{{ route('staff.program-reports.show', $event->report) }}">
                    <span @class(['state', 'state--warning' => $event->action === 'unlock_requested', 'state--success' => $event->action !== 'unlock_requested'])>
                        {{ $event->action === 'unlock_requested' ? 'Unlock requested' : ($event->action === 'resubmitted' ? 'Resubmitted' : 'Submitted') }}
                    </span>
                    <strong>{{ $event->report->program->name }}</strong>
                    <span class="muted small">{{ $event->user->full_name }} · {{ $event->created_at->diffForHumans() }}</span>
                    @if ($event->reason)<span class="inbox-item__reason">“{{ Str::limit($event->reason, 140) }}”</span>@endif
                </a>
            @empty
                <p class="muted small" style="margin:0">Nothing to review. Submitted reports and unlock requests appear here.</p>
            @endforelse
        </section>
    @endif

    {{-- One row per program tag: live totals (admitted viewers, verified revenue), the report's state and a
         one-click export (the locked snapshot once submitted, otherwise live figures). --}}
    @if ($programs->isEmpty())
        <x-empty title="No programs with published screenings yet" />
    @else
        <div class="table-wrap">
            <table class="table program-overview">
                <thead>
                <tr>
                    <th>Program</th><th class="center">Screenings</th><th class="center">M</th><th class="center">F</th>
                    <th class="center">PWD</th><th class="center">Senior</th><th class="num">Revenue</th><th>Report</th><th class="actions">Export</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($programs as $program)
                    @php($report = $program->report)
                    @php($t = $program->totals)
                    <tr>
                        <td>
                            <span class="cell-title">{{ $program->name }}</span>
                            <div class="cell-sub">{{ $t?->films ?? 0 }} {{ Str::plural('film', $t?->films ?? 0) }}</div>
                        </td>
                        <td class="center">{{ $t?->screenings ?? 0 }}<div class="cell-sub">{{ $t?->free_screenings ?? 0 }} free · {{ $t?->paid_screenings ?? 0 }} paid</div></td>
                        <td class="center">{{ $t?->male ?? 0 }}</td>
                        <td class="center">{{ $t?->female ?? 0 }}</td>
                        <td class="center">{{ $t?->pwd ?? 0 }}</td>
                        <td class="center">{{ $t?->senior ?? 0 }}</td>
                        <td class="num nowrap">₱{{ number_format((float) ($t?->revenue ?? 0), 2) }}</td>
                        <td>
                            @if (! $report)
                                <form method="POST" action="{{ route('staff.program-reports.store') }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="program_id" value="{{ $program->program_id }}">
                                    <button type="submit" class="btn btn--text btn--sm">Generate</button>
                                </form>
                            @else
                                <a href="{{ route('staff.program-reports.show', $report) }}" @class(['state', 'state--success' => $report->status === 'submitted', 'state--warning' => $report->status === 'unlocked'])>{{ $report->statusLabel() }}</a>
                                @if ($report->pendingUnlockRequest())<span class="state state--warning">Unlock requested</span>@endif
                            @endif
                        </td>
                        <td class="actions">
                            <div class="row-actions">
                                <a class="btn btn--secondary btn--sm" href="{{ route('staff.program-reports.export-program', [$program, 'xlsx']) }}" title="Manila format{{ $report?->isLocked() ? ' (submitted snapshot)' : ' (live figures)' }}">.xlsx</a>
                                <a class="btn btn--secondary btn--sm" href="{{ route('staff.program-reports.export-program', [$program, 'csv']) }}" title="{{ $report?->isLocked() ? 'Submitted snapshot' : 'Live figures' }}">.csv</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint">Viewers are admitted guests. Exports use the submitted snapshot when a report is locked, otherwise live figures.</p>
    @endif
@endsection
