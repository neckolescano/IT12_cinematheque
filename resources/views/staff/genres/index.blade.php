@extends('layouts.staff')

@section('title', 'Genres')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Catalog</span>
            <h1>Genres</h1>
        </div>
    </div>

    <form class="card reveal" style="margin-bottom:var(--s-5)" method="POST" action="{{ route('staff.genres.store') }}">
        @csrf
        <div class="filters">
            <div class="field @error('genre_name') has-error @enderror">
                <label for="genre_name">New genre <span class="req">*</span></label>
                <input type="text" id="genre_name" name="genre_name" maxlength="50" required value="{{ old('genre_name') }}" placeholder="e.g. Documentary">
                @error('genre_name') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--primary">Add genre</button></div>
        </div>
    </form>

    @if ($genres->isEmpty())
        <div class="card"><x-empty title="No genres yet" /></div>
    @else
        <div class="table-wrap reveal">
            <table class="table">
                <thead><tr><th>Genre</th><th class="num">Movies</th><th></th></tr></thead>
                <tbody>
                @foreach ($genres as $genre)
                    <tr>
                        <td><span class="badge badge--plain">{{ $genre->genre_name }}</span></td>
                        <td class="num">{{ $genre->movies_count }}</td>
                        <td class="actions">
                            <a class="btn btn--ghost btn--sm" href="{{ route('staff.genres.edit', $genre) }}">Edit</a>
                            <form class="inline-form" method="POST" action="{{ route('staff.genres.destroy', $genre) }}"
                                  data-confirm="Delete the genre “{{ $genre->genre_name }}”?" data-confirm-label="Delete genre">
                                @csrf @method('DELETE')
                                <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
