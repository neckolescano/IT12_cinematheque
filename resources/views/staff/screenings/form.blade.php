@extends('layouts.staff')

{{--
    New / Edit screening: a dedicated page. It has a dozen fields across three concerns (what is
    showing, when, admission), is done a few times a week rather than at the door, and deserves
    focus, so it is not squeezed into a drawer. The summary on the right shows what the public
    listing will say, and holds the save action so it stays in reach.
--}}
@section('title', $screening->exists ? 'Edit screening' : 'New screening')

@section('content')
    @php($back = $screening->exists ? route('staff.screenings.show', $screening) : url()->previous(route('staff.dashboard')))
    <div class="page-head">
        <div>
            <a class="back-link" href="{{ $back }}"><x-arrow dir="left" /> {{ $screening->exists ? $screening->event_title : 'Back' }}</a>
            <h1>{{ $screening->exists ? 'Edit screening' : 'New screening' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $screening->exists ? route('staff.screenings.update', $screening) : route('staff.screenings.store') }}" class="form-layout">
        @csrf
        @if ($screening->exists) @method('PUT') @endif

        <div class="stack">
            @include('staff.screenings._fields', ['prefix' => 'page'])
        </div>

        <aside class="form-layout__side">
            <section class="panel summary" aria-labelledby="summary-title" data-screening-summary>
                <h2 class="panel__title" id="summary-title">Summary</h2>
                <dl class="facts">
                    <dt>Title</dt><dd data-sum="title">—</dd>
                    <dt>Type</dt><dd data-sum="kind">—</dd>
                    <dt>Date</dt><dd data-sum="date">—</dd>
                    <dt>Time</dt><dd data-sum="time">—</dd>
                    <dt>Admission</dt><dd data-sum="admission">—</dd>
                    <dt>Capacity</dt><dd>{{ App\Models\Seat::CAPACITY }} seats</dd>
                </dl>
                <div class="summary__actions">
                    <button type="submit" class="btn btn--primary btn--block">{{ $screening->exists ? 'Save changes' : 'Create screening' }}</button>
                    <a class="btn btn--secondary btn--block" href="{{ $back }}">Cancel</a>
                </div>
            </section>
        </aside>
    </form>
@endsection
