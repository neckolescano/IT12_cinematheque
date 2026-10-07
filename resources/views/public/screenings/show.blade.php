@extends('layouts.app')

@section('title', $screening->event_title)

@php
    $movie = $screening->movie;
    $past = $screening->event_date->lt(today());
    $taken = $screening->total_seats - $available;
    $facts = collect([
        $movie?->runtime_minutes ? intdiv($movie->runtime_minutes, 60).'h '.($movie->runtime_minutes % 60).'m' : null,
        $movie?->rating,
        $movie?->release_year,
    ])->filter();
@endphp

@section('content')
    <x-crumbs :items="['Screenings' => route('home'), $screening->event_title => null]" />

    {{-- Everything about the film first; the booking action comes after it. --}}
    <article class="film">
        <x-poster :screening="$screening" class="film__poster" />

        <div class="film__body">
            <span class="eyebrow">{{ $movie ? ($movie->title !== $screening->event_title ? $movie->title : 'Film') : 'Special programme' }}</span>
            <h1 class="display film__title">{{ $screening->event_title }}</h1>

            @if ($facts->isNotEmpty() || $movie?->genres->isNotEmpty())
                <ul class="tags">
                    @foreach ($facts as $fact)<li class="tag tag--filled">{{ $fact }}</li>@endforeach
                    @foreach ($movie?->genres ?? [] as $genre)<li class="tag">{{ $genre->genre_name }}</li>@endforeach
                </ul>
            @endif

            @if ($movie?->synopsis)
                <p class="film__synopsis">{{ $movie->synopsis }}</p>
            @endif

            @if ($movie && ($movie->directors->isNotEmpty() || $movie->actors->isNotEmpty()))
                <dl class="credits">
                    @if ($movie->directors->isNotEmpty())
                        <div><dt>{{ Str::plural('Director', $movie->directors->count()) }}</dt><dd>{{ $movie->directors->pluck('full_name')->join(', ') }}</dd></div>
                    @endif
                    @if ($movie->actors->isNotEmpty())
                        <div><dt>Cast</dt><dd>{{ $movie->actors->pluck('full_name')->join(', ') }}</dd></div>
                    @endif
                </dl>
            @endif

            {{-- This screening, as a ticket, with the action --}}
            <section class="facts-ticket" aria-label="This screening">
                <div class="facts-ticket__stub"><x-date-stub :date="$screening->event_date" size="lg" /></div>
                <div class="facts-ticket__body">
                    <span class="muted small">{{ $screening->event_date->format('l, F j, Y') }}</span>
                    <strong class="facts-ticket__time">{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}</strong>
                    <span class="small">
                        <span class="muted">Admission</span>
                        @if ($screening->isPaid())
                            <strong class="money">₱{{ number_format($screening->price, 0) }}</strong> <span class="muted">per seat · pay online</span>
                        @else
                            <strong>Free</strong> <span class="muted">· approved by staff</span>
                        @endif
                    </span>
                    <span class="bar" aria-hidden="true"><span style="width:{{ $screening->total_seats ? min(100, $taken / $screening->total_seats * 100) : 0 }}%"></span></span>
                    <span @class(['seats-left', 'seats-left--low' => $available > 0 && $available <= 10, 'seats-left--none' => $available === 0])>{{ $available }} of {{ $screening->total_seats }} seats left</span>
                </div>
                <div class="facts-ticket__action">
                    @if ($past)
                        <span class="btn btn--block is-disabled">Screening has ended</span>
                    @elseif ($available > 0)
                        <a class="btn btn--gold btn--lg btn--block" href="{{ route('bookings.create', $screening) }}">Choose seats <x-arrow class="arrow" /></a>
                    @else
                        <span class="btn btn--block is-disabled">Fully booked</span>
                    @endif
                </div>
            </section>
        </div>
    </article>

    @if ($otherDates->isNotEmpty())
        <section class="section-gap" aria-labelledby="other-title">
            <h2 class="section-title" id="other-title">Other dates for this film</h2>
            <div class="ticket-list">
                @foreach ($otherDates as $screening)
                    @include('public.screenings._ticket')
                @endforeach
            </div>
        </section>
    @endif
@endsection
