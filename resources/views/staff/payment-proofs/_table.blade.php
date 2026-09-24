{{-- Proof review rows. Used by the review queue and by each reservation's page. --}}
@if ($proofs->isEmpty())
    <x-empty title="No proofs to show" icon="doc">Uploaded screenshots appear here for review.</x-empty>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                @if ($showReservation) <th>Reservation</th> @endif
                <th>Screenshot</th>
                <th>Submitted</th>
                <th>Status</th>
                <th style="min-width:240px">Review</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($proofs as $proof)
                <tr>
                    @if ($showReservation)
                        @php($r = $proof->payment->reservation)
                        <td>
                            <a style="font-weight:600;letter-spacing:.03em" href="{{ route('staff.reservations.show', $r) }}">{{ $r->booking_reference }}</a>
                            <div>{{ $r->lead_full_name }}</div>
                            <div class="muted small">{{ $r->screening->event_title }}</div>
                            <div class="small" style="margin-top:4px">Due <strong>₱{{ number_format($proof->payment->amount, 2) }}</strong> · {{ $proof->payment->payment_channel ?? 'channel not given' }}</div>
                        </td>
                    @endif
                    <td>
                        <a href="{{ asset('storage/'.$proof->proof_image) }}" target="_blank" rel="noopener" title="Open full size">
                            <img class="proof-thumb" src="{{ asset('storage/'.$proof->proof_image) }}" alt="Proof #{{ $proof->proof_id }}" loading="lazy">
                        </a>
                    </td>
                    <td class="small">{{ $proof->submitted_at->format('M j, Y H:i') }}</td>
                    <td>
                        <x-status :value="$proof->status" />
                        @if ($proof->reviewer)
                            <div class="muted small" style="margin-top:4px">by {{ $proof->reviewer->full_name }} ({{ $proof->reviewer->position ?? '—' }})<br>{{ $proof->reviewed_at?->format('M j, H:i') }}</div>
                        @endif
                        @if ($proof->rejection_reason)
                            <div class="small" style="margin-top:4px">“{{ $proof->rejection_reason }}”</div>
                        @endif
                    </td>
                    <td>
                        @can('review', $proof)
                            <form method="POST" action="{{ route('staff.payment-proofs.accept', $proof) }}" style="margin-bottom:var(--s-3)"
                                  data-confirm="Accept this proof? The payment becomes verified and the reservation is confirmed." data-confirm-label="Accept & verify">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn--success btn--sm btn--block">Accept &amp; verify payment</button>
                            </form>
                            <form method="POST" action="{{ route('staff.payment-proofs.reject', $proof) }}" class="checkin">
                                @csrf @method('PATCH')
                                <div class="field">
                                    <label for="rr{{ $proof->proof_id }}">Rejection reason <span class="req">*</span></label>
                                    <input type="text" id="rr{{ $proof->proof_id }}" name="rejection_reason" maxlength="255" required placeholder="e.g. Amount doesn't match">
                                </div>
                                <button class="btn btn--danger btn--sm" type="submit">Reject</button>
                            </form>
                        @else
                            <span class="muted small">No action needed</span>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
