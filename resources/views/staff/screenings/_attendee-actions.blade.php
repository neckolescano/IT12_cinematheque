{{-- Actions for one guest: Admit (if allowed), or Note + Undo once admitted (while check-in is open, or for the Super Admin). Expects $s (ReservationSeat), $admittable. --}}
@php($att = $s->attendance)
@if ($att)
    @can('update', $att)
        <details class="note-pop">
            <summary class="btn btn--secondary btn--sm" title="Remarks">{{ $att->remarks ? 'Note ●' : 'Note' }}</summary>
            <div class="note-pop__panel">
                <form method="POST" action="{{ route('staff.attendances.update', $att) }}" data-ajax>
                    @csrf @method('PATCH')
                    <div class="field">
                        <label for="rm{{ $att->attendance_id }}">Remarks · seat {{ $s->seat->seat_label }}</label>
                        <textarea id="rm{{ $att->attendance_id }}" name="remarks" rows="3">{{ $att->remarks }}</textarea>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm">Save</button>
                </form>
            </div>
        </details>
        <form method="POST" action="{{ route('staff.attendances.destroy', $att) }}" data-ajax>
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--secondary btn--sm" aria-label="Undo admission of {{ $s->attendee?->full_name }}, seat {{ $s->seat->seat_label }}">Undo</button>
        </form>
    @endcan
@elseif ($admittable)
    <form method="POST" action="{{ route('staff.attendances.store', $s) }}" data-ajax>
        @csrf
        <button type="submit" class="btn btn--primary btn--sm" aria-label="Admit {{ $s->attendee?->full_name }}, seat {{ $s->seat->seat_label }}">Admit</button>
    </form>
@endif
