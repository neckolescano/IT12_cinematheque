@extends('layouts.staff')

@section('title', 'Staff accounts')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Venue</span>
            <h1>Staff accounts</h1>
            <p class="muted small" style="margin:0">AVT and PDO have identical access. Position is recorded for reports only.</p>
        </div>
        @can('create', App\Models\User::class)
            <a class="btn btn--primary" href="{{ route('staff.users.create') }}">+ Add staff account</a>
        @endcan
    </div>

    <div class="table-wrap reveal">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Position</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td style="font-weight:600">{{ $u->full_name }} @if ($u->user_id === auth()->id()) <span class="muted small">(you)</span> @endif</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->position ?? '—' }}</td>
                    <td><x-status :value="$u->is_active ? 'active' : 'inactive'" /></td>
                    <td class="actions">
                        @can('update', $u)
                            <a class="btn btn--ghost btn--sm" href="{{ route('staff.users.edit', $u) }}">Edit</a>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
