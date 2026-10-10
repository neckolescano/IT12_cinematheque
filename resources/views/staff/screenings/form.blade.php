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
                    <dt>Program</dt><dd data-sum="program">—</dd>
                    <dt>Date</dt><dd data-sum="date">—</dd>
                    <dt>Time</dt><dd data-sum="time">—</dd>
                    <dt>Admission</dt><dd data-sum="admission">—</dd>
                    <dt>Capacity</dt><dd>{{ App\Models\Seat::CAPACITY }} seats</dd>
                </dl>
                {{-- Save as draft (staff only) · Review (the customer page as it will look) · Publish --}}
                <p class="status-line">
                    @if ($screening->exists && ! $screening->isDraft())<span class="state state--success">Published</span> Customers can book it.
                    @else<span class="state state--warning">Draft</span> Customers can't see it yet.@endif
                </p>
                <div class="save-actions">
                    <button type="submit" name="intent" value="publish" class="btn btn--primary btn--block">{{ $screening->exists && ! $screening->isDraft() ? 'Save and keep published' : 'Publish' }}</button>
                    <div class="save-actions__row">
                        <button type="submit" name="intent" value="draft" class="btn btn--secondary">Save as draft</button>
                        <button type="submit" name="intent" value="review" class="btn btn--secondary">Review</button>
                    </div>
                    <a class="btn btn--ghost btn--block" href="{{ $back }}">Cancel</a>
                </div>
            </section>
        </aside>
    </form>
@endsection
