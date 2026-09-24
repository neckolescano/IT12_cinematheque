@extends('layouts.app')

@section('title', 'Booking '.$reservation->booking_reference)

@php
    $screening = $reservation->screening;
    $paid = (bool) $payment;
    $cancelled = $reservation->status === 'cancelled';
    $done = $reservation->status === 'confirmed';
    $step = $done ? ($paid ? 4 : 3) : ($paid ? 3 : 2);
    $awaitingReview = $paid && $payment->hasPendingProof();
@endphp

@section('hero')
    <section class="hero hero--compact tex-grid on-dark">
        @include('partials.skyline')
        <div class="container">
            @unless ($cancelled)
                <x-stepper :current="$step" :paid="$paid" class="no-print" />
            @endunless
            <span class="eyebrow">Booking reference</span>
            <h1 style="letter-spacing:.04em">{{ $reservation->booking_reference }}</h1>
            <div class="cluster">
                <x-status :value="$reservation->status" label="Reservation" />
                @if ($paid)
                    <x-status :value="$awaitingReview ? 'awaiting review' : $payment->status" label="Payment" />
                @endif
                <span class="muted small">Save this reference — you'll need it at the door and to come back to this page.</span>
            </div>
        </div>
    </section>
@endsection

@section('content')
    @if ($cancelled)
        <div class="alert alert--error">This reservation has been cancelled. Please contact Cinematheque Centre Davao if you think this is a mistake.</div>
    @elseif ($done)
        <div class="alert alert--success">
            <div><strong>You're all set.</strong> Show the booking reference <strong>{{ $reservation->booking_reference }}</strong> at the entrance. Each attendee is admitted by staff on arrival.</div>
        </div>
    @endif

    <div class="grid grid-2" style="align-items:start">
        {{-- Payment Screen (paid screenings only) --}}
        @if ($paid)
            <section class="card reveal" aria-labelledby="pay-title">
                <div class="card__head">
                    <h2 id="pay-title">Payment</h2>
                    <x-status :value="$payment->status" />
                </div>
                <dl class="kv" style="margin-bottom:var(--s-5)">
                    <dt>Amount due</dt><dd style="font-size:var(--fs-xl);font-weight:800">₱{{ number_format($payment->amount, 2) }}</dd>
                    @if ($payment->payment_channel)
                        <dt>Paid via</dt><dd>{{ $payment->payment_channel }} <span class="muted small">(as you reported)</span></dd>
                    @endif
                </dl>

                @if ($payment->status !== 'verified' && ! $cancelled)
                    <ol class="small" style="padding-left:1.2em;margin:0 0 var(--s-5)">
                        <li>Scan the QR below with your e-wallet or banking app and pay exactly <strong>₱{{ number_format($payment->amount, 2) }}</strong>.</li>
                        <li>Take a screenshot of the successful transfer.</li>
                        <li>Upload the screenshot here. Staff review it; your reservation is confirmed only after they accept it.</li>
                    </ol>

                    <div style="text-align:center;background:var(--bg);border-radius:var(--r-md);padding:var(--s-5);margin-bottom:var(--s-5)">
                        @if ($qrCode)
                            <img src="{{ asset('storage/'.$qrCode->qr_image) }}" alt="Cinematheque Centre Davao payment QR code" style="width:220px;max-width:100%;margin:0 auto;border-radius:var(--r-sm);background:#fff;padding:8px;box-shadow:var(--shadow-md);image-rendering:pixelated">
                            <p class="small muted" style="margin:var(--s-3) 0 0">Cinematheque's official payment QR. This site never takes your money directly.</p>
                        @else
                            <x-empty title="Payment QR not available yet" icon="doc">Please check back later or contact Cinematheque Centre Davao.</x-empty>
                        @endif
                    </div>
                @endif

                @if ($canUploadProof)
                    <form method="POST" action="{{ route('bookings.proof.store', $reservation) }}" enctype="multipart/form-data">
                        @csrf
                        <h3>Upload proof of payment</h3>
                        <div class="field @error('payment_channel') has-error @enderror">
                            <label for="payment_channel">E-wallet or bank used</label>
                            <input type="text" id="payment_channel" name="payment_channel" value="{{ old('payment_channel', $payment->payment_channel) }}" maxlength="30" placeholder="e.g. GCash, Maya, BPI">
                            @error('payment_channel') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field @error('proof_image') has-error @enderror">
                            <label for="proof_image">Screenshot <span class="req">*</span></label>
                            <div class="file-drop">
                                <input type="file" id="proof_image" name="proof_image" accept="image/*" required data-preview="proof-preview">
                                <div class="hint">JPG or PNG, up to 5 MB</div>
                                <img id="proof-preview" class="file-preview" alt="Selected screenshot preview" hidden>
                            </div>
                            @error('proof_image') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="btn btn--primary btn--block">Upload proof</button>
                    </form>
                @elseif ($awaitingReview)
                    <div class="alert alert--warning" style="margin:0">
                        <div><strong>Your proof is awaiting staff review.</strong> This page updates once it's checked — come back with your booking reference.</div>
                    </div>
                @endif

                @if ($payment->proofs->isNotEmpty())
                    <h3 style="margin-top:var(--s-5)">Your uploads</h3>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Submitted</th><th>Status</th><th>Note from staff</th></tr></thead>
                            <tbody>
                            @foreach ($payment->proofs->sortByDesc('submitted_at') as $proof)
                                <tr>
                                    <td>{{ $proof->submitted_at->format('M j, Y H:i') }}</td>
                                    <td><x-status :value="$proof->status" /></td>
                                    <td>{{ $proof->rejection_reason ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        {{-- Booking summary --}}
        <section class="card reveal" aria-labelledby="summary-title">
            <div class="card__head">
                <h2 id="summary-title">Your booking</h2>
                <button type="button" class="btn btn--ghost btn--sm no-print" onclick="window.print()">Print</button>
            </div>
            <div class="screening-card__meta" style="margin-bottom:var(--s-4)">
                <x-date-badge :date="$screening->event_date" />
                <div>
                    <div style="font-weight:700">{{ $screening->event_title }}</div>
                    <div class="muted small">{{ $screening->event_date->format('l, F j, Y') }} · {{ substr($screening->start_time, 0, 5) }}</div>
                </div>
            </div>
            <dl class="kv" style="margin-bottom:var(--s-5)">
                <dt>Booked by</dt><dd>{{ $reservation->lead_full_name }}</dd>
                <dt>Contact</dt><dd>{{ $reservation->lead_contact_no }}</dd>
                <dt>Submitted</dt><dd>{{ $reservation->reservation_datetime->format('M j, Y H:i') }}</dd>
                @unless ($paid)
                    <dt>Admission</dt><dd>Free — no payment needed</dd>
                @endunless
            </dl>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Seat</th><th>Attendee</th></tr></thead>
                    <tbody>
                    @foreach ($reservation->reservationSeats as $rs)
                        <tr>
                            <td><span class="badge badge--gold badge--plain">{{ $rs->seat->seat_label }}</span></td>
                            <td>
                                {{ $rs->attendee?->full_name }}
                                @if ($rs->attendee?->is_lead_reserver) <span class="muted small">(booker)</span> @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @can('view', $reservation)
                <p class="small no-print" style="margin:var(--s-4) 0 0"><a href="{{ route('staff.reservations.show', $reservation) }}">Staff view of this reservation &rarr;</a></p>
            @endcan
        </section>
    </div>
@endsection
