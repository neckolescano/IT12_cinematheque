@extends('layouts.staff')

@section('title', 'Screenings & check-in')

@section('content')
    <header class="page-head">
        <div>
            <h1>Screenings &amp; check-in</h1>
        </div>
    </header>

    <form class="filterbar" method="GET" action="{{ route('staff.screenings.index') }}" data-no-loading>
        <nav class="chips" aria-label="When">
            @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $label)
                <a href="{{ route('staff.screenings.index', array_filter(['when' => $key, 'q' => $q])) }}" @if ($when === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <input type="hidden" name="when" value="{{ $when }}">
        <div class="filterbar__inputs">
            <label class="search">
                <span class="sr-only">Search screenings</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $q }}" placeholder="Search by title">
            </label>
        </div>
    </form>

    @if ($screenings->isEmpty())
        <x-empty :title="$q ? 'No matches' : ($when === 'past' ? 'No past screenings' : 'No upcoming screenings')" />
    @else
        @include('staff.partials.screening-table', ['screenings' => $screenings->getCollection()])
        <div class="pagination">{{ $screenings->links() }}</div>
    @endif
@endsection

