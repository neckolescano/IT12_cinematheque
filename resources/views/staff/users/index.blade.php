@extends('layouts.staff')

@section('title', 'Staff accounts')

@section('content')
    <header class="page-head">
        <div>
            <h1>Staff accounts</h1>
        </div>
        @can('create', App\Models\User::class)
            <a class="btn btn--primary" href="{{ route('staff.users.create') }}">Add account</a>
        @endcan
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Position</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr @class(['is-muted' => ! $u->is_active]) @can('update', $u) data-href="{{ route('staff.users.edit', $u) }}" @endcan>
                    <td><a class="cell-title link-quiet" href="{{ route('staff.users.edit', $u) }}">{{ $u->full_name }}</a> @if ($u->user_id === auth()->id())<span class="muted small">(you)</span>@endif</td>
                    <td>{{ $u->email }}</td>
                    <td>@if ($u->isSuperAdmin())<span class="state state--warning">Super Admin</span>@else Admin @endif</td>
                    <td>{{ $u->position ?? '—' }}</td>
                    <td><span class="state state--{{ $u->is_active ? 'success' : 'neutral' }}">{{ $u->is_active ? 'Active' : 'Deactivated' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
