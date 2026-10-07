@props(['items'])
{{-- Breadcrumb trail: ['Screenings' => url, 'Malvarosa' => url, 'Seats' => null]. The last item is the current page. --}}
<nav {{ $attributes->merge(['class' => 'crumbs']) }} aria-label="Breadcrumb">
    <ol>
        @foreach ($items as $label => $url)
            <li>
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
