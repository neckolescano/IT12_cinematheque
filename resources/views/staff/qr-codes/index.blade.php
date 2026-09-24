@extends('layouts.staff')

@section('title', 'Payment QR code')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Payment screen</span>
            <h1>Payment QR code</h1>
            <p class="muted small" style="margin:0">Moviegoers scan this to pay Cinematheque directly. The system never handles the money.</p>
        </div>
    </div>

    <div class="grid grid-2" style="align-items:start">
        <section class="card reveal">
            <div class="card__head"><h2>Currently shown</h2>@if ($current)<x-status value="active" />@endif</div>
            @if ($current)
                <img src="{{ asset('storage/'.$current->qr_image) }}" alt="Active payment QR" style="width:220px;margin:0 auto var(--s-4);background:#fff;padding:8px;border-radius:var(--r-sm);box-shadow:var(--shadow-md);image-rendering:pixelated">
                <p class="muted small" style="text-align:center;margin:0">Uploaded {{ $current->uploaded_at->format('M j, Y H:i') }} by {{ $current->uploader->full_name }}</p>
            @else
                <x-empty title="No active QR code" icon="doc">Paid bookings can't pay until one is uploaded.</x-empty>
            @endif
        </section>

        @can('create', App\Models\PaymentQrCode::class)
            <section class="card reveal">
                <div class="card__head"><h2>Replace QR code</h2></div>
                <form method="POST" action="{{ route('staff.qr-codes.store') }}" enctype="multipart/form-data"
                      data-confirm="Replace the QR code? Moviegoers will see the new one immediately." data-confirm-label="Replace QR">
                    @csrf
                    <div class="field @error('qr_image') has-error @enderror">
                        <label for="qr_image">New QR image <span class="req">*</span></label>
                        <div class="file-drop">
                            <input type="file" id="qr_image" name="qr_image" accept="image/*" required data-preview="qr-preview">
                            <div class="hint">PNG or JPG, up to 5 MB. The old QR is kept in the history below.</div>
                            <img id="qr-preview" class="file-preview" alt="Selected QR preview" hidden>
                        </div>
                        @error('qr_image') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="btn btn--primary">Upload and make active</button>
                </form>
            </section>
        @endcan
    </div>

    <section class="card reveal" style="margin-top:var(--s-5)">
        <div class="card__head"><h2>History</h2></div>
        @if ($history->isEmpty())
            <x-empty title="No QR codes uploaded yet" icon="doc" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Image</th><th>Uploaded</th><th>By</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($history as $qr)
                        <tr>
                            <td><a href="{{ asset('storage/'.$qr->qr_image) }}" target="_blank" rel="noopener"><img class="proof-thumb" style="width:56px;height:56px" src="{{ asset('storage/'.$qr->qr_image) }}" alt="QR uploaded {{ $qr->uploaded_at->format('M j, Y') }}" loading="lazy"></a></td>
                            <td class="small">{{ $qr->uploaded_at->format('M j, Y H:i') }}</td>
                            <td class="small">{{ $qr->uploader->full_name }}</td>
                            <td><x-status :value="$qr->is_active ? 'active' : 'retired'" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $history->links() }}</div>
        @endif
    </section>
@endsection
