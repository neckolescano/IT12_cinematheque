@extends('layouts.app')

{{--
    About — the mobile app's About screen on the web, with its motion:
      opening: the venue photo comes up and settles, dissolving into the page; the title rises
               line by line; on scroll the photo moves slower than the page (parallax) and the
               title drifts and fades
      chapters: each piece fades and lifts into place as it reaches the reader (scroll-linked,
               reverses when scrolling back)
      timeline: a gold line is drawn down to a reading line; each node fills when reached and its
               entry settles in (year → name → text); the era being read stays pinned at the top
      map:     the four centres placed by their coordinates; parallels first, then the centres
               north to south, Davao last
    Facts from fdcp.ph/about and FDCP's Cinematheque Centres page, as in the app. All motion is
    driven by cinematheque.js ([data-about]); with reduced motion everything is shown settled.
--}}
@section('title', 'About')
@section('main_class', 'page page--about')

@php
    $roles = [
        'Support' => 'Filipino films from development to production, distribution and exhibition.',
        'Promote' => 'Philippine cinema in film markets and festivals, at home and abroad.',
        'Preserve' => 'Films as part of the national cultural heritage, through film archiving.',
    ];
    $eras = [
        ['years' => '1981', 'name' => 'Filipino Motion Picture Development Board', 'short' => 'FMPDB', 'context' => 'An arm of the Cultural Center of the Philippines',
         'founding' => ['5 January 1981', 'Executive Order No. 640-A', 'Set up to operate the Film Academy of the Philippines and to honour those who advanced the motion picture arts and sciences. The same order created a Film Fund to support productions, a Board of Standards for film rating and classification, and a Film Archive for films of historical, cultural or aesthetic value. The Board later became defunct.'],
         'milestones' => []],
        ['years' => '1982–1985', 'name' => 'Experimental Cinema of the Philippines', 'short' => 'ECP', 'context' => 'Affiliated with the Ministry of Tourism',
         'founding' => ['29 January 1982', 'Executive Order No. 770', 'Created to promote the growth of the local film industry. Known mainly as a production company, it was also tasked to lead the Manila International Film Festival, manage the Manila Film Center, run film rating and classification, establish the National Film Archive and support selected films through a film fund.'],
         'milestones' => [
             ['12 June 1982', 'Film Ratings Board', 'Executive Order No. 811', 'Created to raise the quality of Filipino films through a national rating and classification system.'],
         ]],
        ['years' => '1985–2002', 'name' => 'Film Development Foundation of the Philippines, Inc.', 'short' => 'FDFPI', 'context' => 'A non-stock, non-profit corporation',
         'founding' => ['8 August 1985', 'Executive Order No. 1051', 'Established to continue the work of the defunct Experimental Cinema of the Philippines: developing the local film industry, administering film rating and classification, managing the National Film Archive and supporting selected films through a film fund.'],
         'milestones' => [
             ['2 October 1985', 'The Foundation begins its work', null, 'It became functional after registering with the Securities and Exchange Commission.'],
         ]],
        ['years' => '2002–present', 'name' => 'Film Development Council of the Philippines', 'short' => 'FDCP', 'context' => 'Under the Office of the President',
         'founding' => ['7 June 2002', 'Republic Act No. 9167', 'Created to replace the Film Development Foundation. The Cinema Evaluation Board replaced the Film Ratings Board, grading films submitted to FDCP under the Cinema Evaluation System.'],
         'milestones' => [
             ['10 March 2006', 'Oversight delegated to Education', 'Executive Order No. 513', 'The President\'s oversight of the Film Academy of the Philippines and FDCP is delegated to the Secretary of Education.'],
             ['30 October 2007', 'Philippine Film Export Services Office', 'Executive Order No. 674', 'Set up under FDCP\'s supervision to promote the country as a location for international film and television productions.'],
             ['15 June 2009', 'Foreign Affairs joins the export office', 'Executive Order No. 674-A', 'The Department of Foreign Affairs becomes a member of the Philippine Film Export Services Office.'],
             ['20 October 2009', 'Under the Department of Education', 'Executive Order No. 837', 'FDCP is placed under the oversight of the Department of Education.'],
             ['January 2011', 'Philippine Film Archive', null, 'FDCP establishes the Philippine Film Archive, formerly the National Film Archive of the Philippines, to preserve, restore and safeguard Filipino films. It launched on 27 October 2011, the World Day for Audiovisual Heritage.'],
             ['17 April 2012', 'Films deposited with the archive', 'Administrative Order No. 26', 'Government offices are directed to turn over their films and other audio-visual materials to the national film archive for care and preservation.'],
             ['8 November 2018', 'Centennial of Philippine Cinema', 'Proclamation No. 622', '12 September 2019 to 11 September 2020 is declared the Centennial Year of Philippine Cinema, with FDCP as the lead agency.'],
             ['3 February 2021', 'Philippine Film Industry Month', 'Proclamation No. 1085', 'September is declared Philippine Film Industry Month, with FDCP leading its observance.'],
             ['7 January 2022', 'Under Trade and Industry', 'Executive Order No. 161', 'Oversight of FDCP moves from the Department of Education to the Department of Trade and Industry.'],
         ]],
    ];
    // In FDCP's order, with city coordinates for the map (°N, °E).
    $centres = [
        ['Manila', 'Philippine Film Heritage Building, Sta. Lucia Street, Intramuros, Manila', 14.59, 120.98, false],
        ['Iloilo', 'Iznart cor. Solis Sts., Iloilo City', 10.70, 122.56, false],
        ['Davao', 'Palma Gil St., Davao City', 7.07, 125.61, true],
        ['Negros', 'Bacolod City, Negros', 10.68, 122.95, false],
    ];
    // The area the map shows (degrees): taller than wide, like the country.
    [$north, $south, $west, $east] = [15.1, 6.3, 120.2, 126.4];
    $x = fn ($lon) => round(($lon - $west) / ($east - $west) * 100, 2);
    $y = fn ($lat) => round(($north - $lat) / ($north - $south) * 100, 2);
    // "30 October 2007" → ["30 OCT", "2007"]
    $split = function (string $date) {
        $parts = explode(' ', $date);
        $year = array_pop($parts);

        return [strtoupper(implode(' ', array_map(fn ($p) => mb_substr($p, 0, 3), $parts))), $year];
    };
@endphp

@section('hero')
    <section class="opening" data-opening>
        <div class="opening__media" data-opening-media>
            <img class="opening__photo" src="{{ asset('images/about/ccd_facade.jpg') }}" alt="">
        </div>
        @include('partials.skyline')
        <div class="opening__fade" aria-hidden="true"></div>
        <div class="about-col opening__title" data-opening-title>
            <span class="eyebrow opening__eyebrow">About · An FDCP Cinematheque Centre</span>
            <h1 class="display opening__lines" aria-label="Cinematheque Centre Davao">
                @foreach (['Cinematheque', 'Centre', 'Davao'] as $i => $line)
                    <span class="opening__mask" aria-hidden="true"><span style="--i: {{ $i }}">{{ $line }}</span></span>
                @endforeach
            </h1>
            <p class="opening__place"><span aria-hidden="true"></span>Palma Gil St. · Davao City</p>
        </div>
    </section>
@endsection

@section('content')
    <div class="about" data-about>
        {{-- 01 --}}
        <section class="about-col chapter" aria-labelledby="ch1">
            <p class="chapter__mark" data-reveal><b>01</b><i aria-hidden="true"></i>The Cinematheque</p>
            <h2 class="chapter__statement" id="ch1" data-reveal>An alternative screen for independent, classic and world cinema.</h2>
            <p class="reading" data-reveal>Commercial cinemas mostly show what the market dictates. The Film Development Council of the Philippines runs its own Cinematheque Centres around the country, as venues that bring more diverse films to the regions.</p>
            <p class="reading muted" data-reveal>They are more than places to watch. Each centre is where a local film community comes together — nurturing its own filmmakers and building an audience for its own stories.</p>
            <p class="thread" data-reveal><strong class="display">Cinematheque Centre Davao</strong><span class="muted">is one of these centres, on Palma Gil St., Davao City.</span></p>
        </section>

        {{-- 02 --}}
        <section class="about-col chapter" aria-labelledby="ch2">
            <p class="chapter__mark" data-reveal><b>02</b><i aria-hidden="true"></i>The institution</p>
            <h2 class="display chapter__title" id="ch2" data-reveal>Film Development Council of the Philippines</h2>
            <span class="fdcp-rule" aria-hidden="true" data-reveal></span>
            <p class="reading" data-reveal>FDCP is the government agency responsible for the growth and development of the Philippine film industry — for its economic, cultural and educational contribution to the nation — and for preserving the country's film heritage.</p>
            <dl class="roles">
                @foreach ($roles as $verb => $text)
                    <div data-reveal><dt class="display">{{ $verb }}</dt><dd>{{ $text }}</dd></div>
                @endforeach
            </dl>
        </section>

        {{-- 03 --}}
        <section class="about-col chapter" aria-labelledby="ch3">
            <p class="chapter__mark" data-reveal><b>03</b><i aria-hidden="true"></i>Our story</p>
            <h2 class="display chapter__title" id="ch3" data-reveal>From a film board to a film council</h2>
            <p class="chapter__lead" data-reveal>Four institutions carried the same work — developing Philippine film and keeping its archive — before FDCP took its present name in 2002.</p>

            <div class="timeline" data-timeline>
                <span class="timeline__track" aria-hidden="true"><span data-timeline-ink></span></span>

                <div class="tl-row tl-row--prologue" data-tl-row data-node-y="40">
                    <span class="tl-node tl-node--prologue" aria-hidden="true"></span>
                    <span class="tl-big tl-big--faint s1">1919</span>
                    <div class="s2">
                        <span class="eyebrow eyebrow--muted">12 September 1919</span>
                        <p class="tl-prologue-title">Dalagang Bukid</p>
                    </div>
                    <p class="reading muted s3">Jose Nepomuceno's Dalagang Bukid premieres — the first feature film directed and produced by a Filipino. Its premiere date is marked as the birth of Philippine cinema.</p>
                </div>

                @foreach ($eras as $e => $era)
                    <div class="tl-row tl-row--era" data-tl-row data-era="{{ $e }}" data-node-y="48">
                        <span class="tl-node tl-node--era" aria-hidden="true"></span>
                        <span class="tl-big s1" aria-hidden="true">{{ $era['years'] }}</span>
                        <div class="s2">
                            <span class="eyebrow">{{ $era['context'] }}</span>
                            <h3 class="display tl-era-name"><span class="sr-only">{{ $era['years'] }}. </span>{{ $era['name'] }}</h3>
                        </div>
                        <div class="s3">
                            <p class="tl-basis">{{ $era['founding'][0] }}  ·  {{ $era['founding'][1] }}</p>
                            <p class="reading">{{ $era['founding'][2] }}</p>
                        </div>
                    </div>
                    @foreach ($era['milestones'] as [$date, $title, $basis, $text])
                        @php([$dayMonth, $year] = $split($date))
                        <div class="tl-row tl-row--milestone" data-tl-row data-node-y="14" data-quick>
                            <span class="tl-node tl-node--milestone" aria-hidden="true"></span>
                            <div class="tl-milestone s1">
                                <span class="tl-when" aria-label="{{ $date }}"><b>{{ $year }}</b><small>{{ $dayMonth }}</small></span>
                                <div>
                                    <strong>{{ $title }}</strong>
                                    @if ($basis)<span class="tl-ms-basis">{{ $basis }}</span>@endif
                                    <p>{{ $text }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
                <span data-timeline-end></span>
            </div>
        </section>

        {{-- 04 --}}
        <section class="about-col chapter" aria-labelledby="ch4">
            <p class="chapter__mark" data-reveal><b>04</b><i aria-hidden="true"></i>The Centres</p>
            <h2 class="display chapter__title" id="ch4" data-reveal>Across the country</h2>
            <p class="reading" data-reveal>FDCP runs its Cinematheque Centres as venues for its programmes, reaching local film communities around the country. Its website currently lists these four.</p>

            <figure class="centres-map" data-map role="img" aria-label="Map of the Cinematheque Centres, north to south: Manila; Iloilo and Negros; Davao, this centre.">
                @foreach ([14, 10, 7] as $lat)
                    <span class="centres-map__parallel" style="top: {{ $y($lat) }}%"><span>{{ $lat }}°N</span></span>
                @endforeach
                @foreach ($centres as [$name, $address, $lat, $lon, $here])
                    @php($arrive = $here ? [0.6, 0.95] : [0.1 + 0.35 * (($north - $lat) / ($north - $south)), 0.4 + 0.35 * (($north - $lat) / ($north - $south))])
                    <span @class(['centres-map__dot', 'is-here' => $here, 'is-left' => $x($lon) > 60, 'is-lifted' => $name === 'Iloilo'])
                          style="left: {{ $x($lon) }}%; top: {{ $y($lat) }}%" data-arrive="{{ round($arrive[0], 3) }},{{ round($arrive[1], 3) }}" aria-hidden="true">
                        <i></i>
                        <span class="centres-map__label display">{{ $name }}@if ($here)<small class="eyebrow">You are here</small>@endif</span>
                    </span>
                @endforeach
            </figure>
            <p class="centres-map__note">North to south · placed by map coordinates</p>

            <ul class="centres">
                @foreach ($centres as [$name, $address, $lat, $lon, $here])
                    <li @class(['is-here' => $here]) data-reveal>
                        <strong class="display">Cinematheque Centre {{ $name }}@if ($here) <span class="eyebrow">You are here</span>@endif</strong>
                        <span class="muted">{{ $address }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Back where the story started --}}
        <section class="about-col chapter chapter--closing">
            <div data-reveal>@include('partials.brand')</div>
            <h2 class="display chapter__title" data-reveal>Cinematheque Centre Davao</h2>
            <p class="muted reading" data-reveal>Palma Gil St., Davao City</p>
            <p data-reveal><a class="btn btn--gold btn--lg" href="{{ route('home') }}">See what's showing <x-arrow class="arrow" /></a></p>
            <p class="sources" data-reveal>Sources: Film Development Council of the Philippines — fdcp.ph/about (Our Story) and fdcp.ph Cinematheque Centres. Wording shortened; dates and names as published. Opening photo: Cinematheque Centre Davao (FDCP) on Instagram, 27 October 2022.</p>
        </section>

        {{-- The era being read, pinned under the header like an intertitle --}}
        <div class="era-strip" data-era-strip aria-hidden="true">
            <div class="era-strip__bar"><div class="about-col era-strip__inner">
                <b data-era-years></b><i></i><span data-era-name></span></div>
            </div>
        </div>
        <script type="application/json" data-era-names>@json(collect($eras)->map(fn ($e) => ['years' => strtoupper($e['years']), 'name' => strtoupper($e['name']), 'short' => $e['short']]))</script>
    </div>
@endsection
