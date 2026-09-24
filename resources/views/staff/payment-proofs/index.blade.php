@extends('layouts.staff')

@section('title', 'Payment proofs')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Verification</span>
            <h1>Payment proofs</h1>
            <p class="muted small" style="margin:0">Compare each screenshot with the amount due. Accepting verifies the payment and confirms the reservation.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Filter proofs">
        @foreach ([...App\Models\PaymentProof::STATUSES, 'all'] as $s)
            <a href="{{ route('staff.payment-proofs.index', ['status' => $s]) }}" @if ($s === $status) aria-current="page" @endif>{{ ucfirst($s) }}</a>
        @endforeach
    </nav>

    <div class="reveal">
        @include('staff.payment-proofs._table', ['proofs' => $proofs, 'showReservation' => true])
    </div>
    <div class="pagination">{{ $proofs->links() }}</div>
@endsection
