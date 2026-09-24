@extends('layouts.staff')

@section('title', 'Reservations')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Bookings & admission</span>
            <h1>Reservations</h1>
        </div>
    </div>

    <form class="card reveal" style="margin-bottom:var(--s-5)" method="GET" action="{{ route('staff.reservations.index') }}" data-no-loading>
        <div class="filters">
            <div class="field">
                <label for="q">Reference, name or contact no.</label>
                <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="e.g. CCD-7K2Q or Santos">
            </div>
            <div class="field">
                <label for="screening_id">Screening</label>
                <select id="screening_id" name="screening_id">
                    <option value="">All screenings</option>
                    @foreach ($screenings as $s)
                        <option value="{{ $s->screening_id }}" @selected(($filters['screening_id'] ?? null) == $s->screening_id)>
                            {{ $s->event_date->format('M j') }} — {{ $s->event_title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Any status</option>
                    @foreach (App\Models\Reservation::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--dark">Filter</button>
                <a class="btn btn--ghost" href="{{ route('staff.reservations.index') }}">Reset</a>
            </div>
        </div>
    </form>

    @if ($reservations->isEmpty())
        <div class="card"><x-empty title="No reservations match" icon="doc">Try a different reference, name or filter.</x-empty></div>
    @else
        <div class="table-wrap reveal">
            <table class="table">
                <thead><tr><th>Reference</th><th>Screening</th><th>Booked by</th><th class="num">Seats</th><th>Reservation</th><th>Payment</th><th>Submitted</th></tr></thead>
                <tbody>
                @foreach ($reservations as $reservation)
                    <tr>
                        <td><a style="font-weight:600;letter-spacing:.03em" href="{{ route('staff.reservations.show', $reservation) }}">{{ $reservation->booking_reference }}</a></td>
                        <td>{{ $reservation->screening->event_title }}<div class="muted small">{{ $reservation->screening->event_date->format('M j, Y') }}</div></td>
                        <td>{{ $reservation->lead_full_name }}<div class="muted small">{{ $reservation->lead_contact_no }}</div></td>
                        <td class="num">{{ $reservation->reservation_seats_count }}</td>
                        <td><x-status :value="$reservation->status" /></td>
                        <td>
                            @if ($reservation->payment)
                                <x-status :value="$reservation->payment->status" />
                                <div class="muted small">₱{{ number_format($reservation->payment->amount, 2) }}</div>
                            @else
                                <span class="muted">Free</span>
                            @endif
                        </td>
                        <td class="small">{{ $reservation->reservation_datetime->format('M j, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $reservations->links() }}</div>
    @endif
@endsection
