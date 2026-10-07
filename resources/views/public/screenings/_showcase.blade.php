{{-- Poster showcase: one card per film, in Now Showing / Advance Booking / Coming Soon / Special Screenings tabs,
     with the Free/Paid filter. Without JS every tab's panel is shown in turn (with its heading); cinematheque.js
     turns the bar into real tabs. Showtimes and seats are on each film's page. --}}
@php
    $active = $tabs->first(fn ($tab) => $tab->films->isNotEmpty())?->key ?? 'now';
@endphp
<div class="showcase" id="showcase" data-showcase>
    <div class="showcase__bar">
        <nav class="showcase__tabs" aria-label="Screenings by date" data-tablist>
            @foreach ($tabs as $tab)
                <a id="tab-{{ $tab->key }}" href="#showing-{{ $tab->key }}" data-tab @if ($tab->key === $active) aria-selected="true" @endif>
                    {{ $tab->label }} <span class="showcase__count">{{ $tab->films->count() }}</span>
                </a>
            @endforeach
        </nav>
        <nav class="showcase__admission" aria-label="Admission">
            @foreach (['' => 'All', 'free' => 'Free', 'paid' => 'Paid'] as $key => $label)
                <a href="{{ route('home', array_filter(['q' => $q, 'type' => $key])) }}" data-keep-tab @if ($type === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($total === 0)
        <x-empty :title="($q || $type) ? 'No matching screenings' : 'No upcoming screenings'">
            @if ($q || $type)<a href="{{ route('home') }}">Clear search and filter</a>@endif
        </x-empty>
    @else
        @foreach ($tabs as $tab)
            <section @class(['showcase__panel', 'is-active' => $tab->key === $active]) id="showing-{{ $tab->key }}" aria-labelledby="showing-{{ $tab->key }}-title">
                <h2 class="section-title showcase__heading" id="showing-{{ $tab->key }}-title">{{ $tab->label }}</h2>
                @if ($tab->films->isEmpty())
                    <p class="showcase__empty">Nothing in {{ $tab->label }} right now{{ $q || $type ? ' for this search' : '' }}.</p>
                @else
                    <div class="showcase__grid">
                        @foreach ($tab->films as $film)
                            @include('public.screenings._film-card')
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    @endif
</div>
