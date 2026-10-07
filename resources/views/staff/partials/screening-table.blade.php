{{--
    Screenings as a table, grouped by day: time · screening · seats booked · admitted. The row opens the screening.
    Used by the Attendance page and the dashboard. Expects $screenings (with reserved_count,
    admitted_count, pending_count); optional $grouped (default true).
--}}
@php($grouped = $grouped ?? true)
<div class="table-wrap">
    <table class="table screenings">
        <thead>
        <tr><th class="col-time">Time</th><th>Screening</th><th>Booked</th><th class="num">Admitted</th></tr>
        </thead>
        @foreach ($grouped ? $screenings->groupBy(fn ($s) => $s->event_date->toDateString()) : collect(['' => $screenings]) as $date => $day)
            <tbody>
            @if ($grouped)
                @php($d = \Carbon\Carbon::parse($date))
                <tr class="group-row"><th colspan="4">
                    {{ $d->isToday() ? 'Today' : ($d->isTomorrow() ? 'Tomorrow' : ($d->isYesterday() ? 'Yesterday' : $d->format('l'))) }}
                    <span>{{ $d->format('F j, Y') }}</span>
                </th></tr>
            @endif
            @foreach ($day as $s)
                @php($past = $s->event_date->lt(today()))
                <tr data-href="{{ route('staff.screenings.show', $s) }}">
                    <td class="col-time nowrap">{{ \Carbon\Carbon::parse($s->start_time)->format('g:i A') }}</td>
                    <td>
                        <a class="cell-title link-quiet" href="{{ route('staff.screenings.show', $s) }}">{{ $s->event_title }}</a>
                        <div class="cell-sub">
                            {{ $s->isPaid() ? '₱'.number_format($s->price, 0) : 'Free' }}
                            @if ($s->pending_count) · <span class="text-warning">{{ $s->pending_count }} {{ $s->isPaid() ? 'awaiting payment' : 'to approve' }}</span>@endif
                        </div>
                    </td>
                    <td class="nowrap">
                        <span class="fill"><span style="width:{{ $s->total_seats ? min(100, $s->reserved_count / $s->total_seats * 100) : 0 }}%"></span></span>
                        <span class="num-inline">{{ $s->reserved_count }} / {{ $s->total_seats }}</span>
                    </td>
                    <td class="num">{{ ($past || $s->event_date->isToday()) ? $s->admitted_count : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        @endforeach
    </table>
</div>
