@extends('layouts.staff')

@section('title', $label.'s')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Catalog</span>
            <h1>{{ $label }}s</h1>
        </div>
    </div>

    <form class="card reveal" style="margin-bottom:var(--s-5)" method="POST" action="{{ route($routePrefix.'.store') }}">
        @csrf
        <h2 style="font-size:var(--fs-lg)">Add {{ strtolower($label) }}</h2>
        <div class="filters">
            <div class="field @error('first_name') has-error @enderror">
                <label for="first_name">First name <span class="req">*</span></label>
                <input type="text" id="first_name" name="first_name" maxlength="50" required value="{{ old('first_name') }}">
                @error('first_name') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="middle_name">Middle name</label>
                <input type="text" id="middle_name" name="middle_name" maxlength="50" value="{{ old('middle_name') }}">
            </div>
            <div class="field @error('last_name') has-error @enderror">
                <label for="last_name">Last name <span class="req">*</span></label>
                <input type="text" id="last_name" name="last_name" maxlength="50" required value="{{ old('last_name') }}">
                @error('last_name') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--primary">Add</button></div>
        </div>
    </form>

    @if ($people->isEmpty())
        <div class="card"><x-empty title="No {{ strtolower($label) }}s yet" /></div>
    @else
        <div class="table-wrap reveal">
            <table class="table">
                <thead><tr><th>Name</th><th class="num">Movies</th><th></th></tr></thead>
                <tbody>
                @foreach ($people as $person)
                    <tr>
                        <td style="font-weight:500">{{ $person->last_name }}, {{ $person->first_name }} {{ $person->middle_name }}</td>
                        <td class="num">{{ $person->movies_count }}</td>
                        <td class="actions">
                            <a class="btn btn--ghost btn--sm" href="{{ route($routePrefix.'.edit', $person) }}">Edit</a>
                            <form class="inline-form" method="POST" action="{{ route($routePrefix.'.destroy', $person) }}"
                                  data-confirm="Delete {{ $person->full_name }}? They'll be removed from every movie credit." data-confirm-label="Delete">
                                @csrf @method('DELETE')
                                <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $people->links() }}</div>
    @endif
@endsection
