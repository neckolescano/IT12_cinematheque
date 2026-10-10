@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
    <header class="page-head">
        <div>
            <span class="eyebrow">{{ now()->format('l, F j, Y') }}</span>
            <h1>Dashboard</h1>
            <p class="figures">
                <a href="{{ route('staff.screenings.index') }}"><b>{{ $summary['upcoming'] }}</b> upcoming {{ Str::plural('screening', $summary['upcoming']) }}</a>
                <span><b>{{ $summary['awaiting_payment'] }}</b> awaiting payment</span>
                <a href="{{ route('staff.reports.index') }}"><b>₱{{ number_format($summary['paid_7d'], 0) }}</b> paid this week</a>
            </p>
        </div>
        @can('create', App\Models\Screening::class)
            <a class="btn btn--primary" href="{{ route('staff.screenings.create') }}">New screening</a>
        @endcan
    </header>

    @unless ($paymentsEnabled)
        <div class="alert alert--warning"><div><strong>PayMongo is not configured.</strong> Paid bookings can’t be paid. Set <code>PAYMONGO_SECRET_KEY</code> in <code>.env</code>.</div></div>
    @endunless
    @unless ($mailConfigured)
        <div class="alert alert--warning"><div><strong>Email is not configured.</strong> E-tickets can’t be sent. Set the <code>MAIL_*</code> values in <code>.env</code>.</div></div>
    @endunless

    <div class="split">
        <div class="split__main">
            <section class="block" aria-labelledby="door-title">
                <div class="block__head"><h2 id="door-title">Today</h2></div>
                @if ($today->isEmpty())
                    <p class="quiet">
                        No screenings today.
                        @if ($week->isNotEmpty())
                            Next: <a href="{{ route('staff.screenings.show', $week->first()) }}">{{ $week->first()->event_title }}</a>, {{ $week->first()->event_date->format('l') }} at {{ \Carbon\Carbon::parse($week->first()->start_time)->format('g:i A') }}.
                        @elseif ($next)
                            Next: <a href="{{ route('staff.screenings.show', $next) }}">{{ $next->event_title }}</a> on {{ $next->event_date->format('M j') }}.
                        @endif
                    </p>
                @else
                    @include('staff.partials.screening-table', ['screenings' => $today, 'grouped' => false])
                @endif
            </section>

            <section class="block" aria-labelledby="week-title">
                <div class="block__head">
                    <h2 id="week-title">Next 7 days</h2>
                    <a href="{{ route('staff.screenings.index') }}">All screenings</a>
                </div>
                @if ($week->isEmpty())
                    <p class="quiet">Nothing scheduled.</p>
                @else
                    @include('staff.partials.screening-table', ['screenings' => $week])
                @endif
            </section>
        </div>

        <aside class="split__side">
            {{-- 3. Waiting on staff: refunds to make (bookings are approved automatically) --}}
            <section class="block" aria-labelledby="action-title">
                <div class="block__head">
                    <h2 id="action-title">Needs action @if ($refunds->count())<span class="count count--warn">{{ $refunds->count() }}</span>@endif</h2>
                </div>
                @if ($refunds->isEmpty())
                    <p class="quiet">Nothing to refund.</p>
                @else
                    <h3 class="day">Refunds due</h3>
                    <ul class="rows rows--compact">
                        @foreach ($refunds as $r)
                            @include('staff.partials.booking-row', ['r' => $r, 'showState' => false])
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="block" aria-labelledby="recent-title">
                <div class="block__head">
                    <h2 id="recent-title">Recent bookings</h2>
                </div>
                @if ($recent->isEmpty())
                    <p class="quiet">No bookings yet.</p>
                @else
                    <ul class="rows rows--compact">
                        @foreach ($recent as $r)
                            @include('staff.partials.booking-row', ['r' => $r])
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>
@endsection
