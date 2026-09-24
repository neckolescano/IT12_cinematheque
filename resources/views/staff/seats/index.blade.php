@extends('layouts.staff')

@section('title', 'Seats')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Venue</span>
            <h1>Seats</h1>
            <p class="muted small" style="margin:0">{{ $seats->count() }} physical seats, reused for every screening. A seat that has ever been reserved can't be removed.</p>
        </div>
    </div>

    <form class="card reveal" style="margin-bottom:var(--s-5)" method="POST" action="{{ route('staff.seats.store') }}">
        @csrf
        <div class="filters">
            <div class="field @error('seat_label') has-error @enderror">
                <label for="seat_label">Seat label <span class="req">*</span></label>
                <input type="text" id="seat_label" name="seat_label" maxlength="10" required value="{{ old('seat_label') }}" placeholder="e.g. K1">
                @error('seat_label') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="section">Section</label>
                <input type="text" id="section" name="section" maxlength="30" value="{{ old('section') }}" placeholder="e.g. Main">
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--primary">Add seat</button></div>
        </div>
    </form>

    @if ($seats->isEmpty())
        <div class="card"><x-empty title="No seats yet">Add the venue's seats so moviegoers can pick them.</x-empty></div>
    @else
        <div class="table-wrap reveal">
            <table class="table">
                <thead><tr><th>Seat</th><th>Section</th><th class="num">Times reserved</th><th></th></tr></thead>
                <tbody>
                @foreach ($seats as $seat)
                    <tr>
                        <td><span class="badge badge--gold badge--plain">{{ $seat->seat_label }}</span></td>
                        <td>{{ $seat->section ?? '—' }}</td>
                        <td class="num">{{ $seat->reservation_seats_count }}</td>
                        <td class="actions">
                            @can('delete', $seat)
                                <form class="inline-form" method="POST" action="{{ route('staff.seats.destroy', $seat) }}"
                                      data-confirm="Remove seat {{ $seat->seat_label }} from the venue?" data-confirm-label="Remove seat">
                                    @csrf @method('DELETE')
                                    <button class="btn btn--danger btn--sm" type="submit">Remove</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
