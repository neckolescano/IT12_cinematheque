@extends('layouts.staff')

@section('title', 'Reservations')

@section('content')
    <div class="page-head">
        <div>
            <h1>Reservations</h1>
            <p>Every booking across all screenings. Search by reference, booker, attendee, email or phone.</p>
        </div>
    </div>

    <div class="card card--flush">
        <form class="toolbar" method="GET" action="{{ route('staff.reservations.index') }}" data-no-loading style="align-items:flex-end">
            <div class="filters" style="flex:1">
                <div class="field" style="margin:0">
                    <label for="q">Search</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="CCD-7K2Q, Santos, ana@…">
                </div>
                <div class="field" style="margin:0">
                    <label for="screening_id">Screening</label>
                    <select id="screening_id" name="screening_id">
                        <option value="">All screenings</option>
                        @foreach ($screenings as $s)
                            <option value="{{ $s->screening_id }}" @selected(($filters['screening_id'] ?? null) == $s->screening_id)>{{ $s->event_date->format('M j') }} — {{ $s->event_title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="status">Reservation</label>
                    <select id="status" name="status">
                        <option value="">Any status</option>
                        @foreach (App\Models\Reservation::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status === 'confirmed' ? 'Approved' : ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="payment">Payment</label>
                    <select id="payment" name="payment">
                        <option value="">Any</option>
                        <option value="paid" @selected(($filters['payment'] ?? null) === 'paid')>Paid</option>
                        <option value="unpaid" @selected(($filters['payment'] ?? null) === 'unpaid')>Unpaid</option>
                        <option value="free" @selected(($filters['payment'] ?? null) === 'free')>Free screening</option>
                    </select>
                </div>
            </div>
            <div class="cluster">
                <button type="submit" class="btn btn--dark">Apply</button>
                @if (array_filter($filters))<a class="btn btn--ghost" href="{{ route('staff.reservations.index') }}">Clear</a>@endif
            </div>
        </form>

        @if ($reservations->isEmpty())
            <x-empty title="No reservations match" icon="doc">Try a different search or filter.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Booked by</th><th>Screening</th><th class="num">Seats</th><th>Reservation</th><th>Payment</th><th>Submitted</th></tr></thead>
                    <tbody>
                    @foreach ($reservations as $r)
                        <tr data-href="{{ route('staff.reservations.show', $r) }}">
                            <td><a style="font-weight:600;letter-spacing:.02em" href="{{ route('staff.reservations.show', $r) }}">{{ $r->booking_reference }}</a></td>
                            <td><div class="cell-title">{{ $r->lead_full_name }}</div><div class="cell-sub">{{ $r->lead_email ?? $r->lead_contact_no }}</div></td>
                            <td>
                                <a class="link-quiet" href="{{ route('staff.screenings.show', $r->screening) }}">{{ $r->screening->event_title }}</a>
                                <div class="cell-sub">{{ $r->screening->event_date->format('M j, Y') }}</div>
                            </td>
                            <td class="num">{{ $r->reservation_seats_count }}</td>
                            <td><x-status :value="$r->status === 'confirmed' ? 'approved' : $r->status" /></td>
                            <td>
                                @if (! $r->payment)
                                    <span class="badge badge--neutral badge--plain">Free</span>
                                @else
                                    <x-status :value="$r->payment->isPaid() ? 'paid' : 'unpaid'" />
                                    <div class="cell-sub">₱{{ number_format($r->payment->amount, 2) }}</div>
                                @endif
                            </td>
                            <td class="cell-sub">{{ $r->reservation_datetime->format('M j, g:i A') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="pagination">{{ $reservations->links() }}</div>
@endsection
