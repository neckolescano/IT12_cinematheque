@extends('layouts.staff')

@section('title', $q !== '' ? 'Search: '.$q : 'Search')

@section('content')
    @php($total = $bookings->count() + $screenings->count() + $films->count())
    <header class="page-head">
        <div>
            <span class="eyebrow">Search</span>
            <h1>{{ $q !== '' ? '“'.$q.'”' : 'Search' }}</h1>
            <p>
                @if (mb_strlen($q) < 2)
                    Enter at least 2 characters.
                @else
                    {{ $total }} {{ Str::plural('result', $total) }}
                @endif
            </p>
        </div>
    </header>

    @if (mb_strlen($q) >= 2 && $total === 0)
        <x-empty title="No results" icon="doc">Try a booking reference, email or mobile number.</x-empty>
    @endif

    @if ($bookings->isNotEmpty())
        <section class="block">
            <div class="block__head"><h2>Bookings <span class="count">{{ $bookings->count() }}</span></h2>
                <a href="{{ route('staff.reservations.index', ['q' => $q]) }}">View all</a></div>
            <ul class="rows">
                @foreach ($bookings as $r)
                    @include('staff.partials.booking-row', ['r' => $r])
                @endforeach
            </ul>
        </section>
    @endif

    @if ($screenings->isNotEmpty())
        <section class="block">
            <div class="block__head"><h2>Screenings <span class="count">{{ $screenings->count() }}</span></h2></div>
            <ul class="rows">
                @foreach ($screenings as $s)
                    <li class="row" data-href="{{ route('staff.screenings.show', $s) }}">
                        <span class="row__when"><b>{{ $s->event_date->format('D, M j') }}</b> {{ \Carbon\Carbon::parse($s->start_time)->format('g:i A') }}</span>
                        <div class="row__main">
                            <a class="row__title" href="{{ route('staff.screenings.show', $s) }}">{{ $s->event_title }}</a>
                            <span class="row__meta">{{ $s->isPaid() ? '₱'.number_format($s->price, 0) : 'Free' }}{{ $s->event_date->lt(today()) ? ' · past' : '' }}</span>
                        </div>
                        <span class="row__fig">{{ $s->reserved_count }}<small>/{{ $s->total_seats }} booked</small></span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($films->isNotEmpty())
        <section class="block">
            <div class="block__head"><h2>Films <span class="count">{{ $films->count() }}</span></h2></div>
            <ul class="rows">
                @foreach ($films as $m)
                    <li class="row" data-href="{{ route('staff.movies.edit', $m) }}">
                        @if ($m->posterUrl())<img class="row__poster" src="{{ $m->posterUrl() }}" alt="">@else<span class="row__poster"></span>@endif
                        <div class="row__main">
                            <a class="row__title" href="{{ route('staff.movies.edit', $m) }}">{{ $m->title }}</a>
                            <span class="row__meta">{{ $m->release_year ? $m->release_year.' · ' : '' }}{{ $m->screenings_count }} {{ Str::plural('screening', $m->screenings_count) }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
