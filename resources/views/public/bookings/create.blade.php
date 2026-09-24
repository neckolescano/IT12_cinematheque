@extends('layouts.app')

@section('title', 'Reserve — '.$screening->event_title)

@php($max = \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION)

@section('hero')
    <section class="hero hero--compact tex-grid on-dark">
        @include('partials.skyline')
        <div class="container">
            <a class="crumb" href="{{ route('screenings.show', $screening) }}">&larr; {{ $screening->event_title }}</a>
            <x-stepper :current="$selected->isEmpty() ? 1 : 2" :paid="$screening->isPaid()" />
            <h1>{{ $selected->isEmpty() ? 'Choose your seats' : 'Who is coming?' }}</h1>
            <div class="cluster muted">
                <span>{{ $screening->event_date->format('D, M j, Y') }} · {{ substr($screening->start_time, 0, 5) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2).' per seat' : 'Free admission' }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $available }} seats left</span>
            </div>
        </div>
    </section>
@endsection

@section('content')
    @if ($selected->isEmpty())
        {{-- Step 1: seat map --}}
        <form method="GET" action="{{ route('bookings.create', $screening) }}" class="card fade-swap"
              data-seat-picker data-max="{{ $max }}" data-price="{{ $screening->isPaid() ? $screening->price : 0 }}">
            <div class="screen-bar"><span>Screen</span></div>

            @foreach ($seats->sortBy('seat_id')->groupBy('section') as $section => $sectionSeats)
                <div class="seat-section">
                    <h3>{{ $section ?: 'Seats' }}</h3>
                    <div class="seat-grid">
                        @foreach ($sectionSeats as $seat)
                            @php($taken = in_array($seat->seat_id, $takenSeatIds, true))
                            <label class="seat" title="{{ $taken ? 'Seat '.$seat->seat_label.' is taken' : 'Seat '.$seat->seat_label }}">
                                <input type="checkbox" name="seats[]" value="{{ $seat->seat_id }}" data-label="{{ $seat->seat_label }}"
                                       @disabled($taken)>
                                <span>{{ $seat->seat_label }}<span class="sr-only">{{ $taken ? ' (taken)' : '' }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="seat-legend" aria-hidden="true">
                <span><i></i>Available</span>
                <span><i class="is-selected"></i>Your pick</span>
                <span><i class="is-taken"></i>Taken</span>
            </div>

            <div class="summary-bar">
                <div>
                    <strong data-seat-count>Pick your seats</strong>
                    <span class="small" style="opacity:.8"> · <span data-seat-list>up to {{ $max }} per booking</span></span>
                    @if ($screening->isPaid())
                        <span class="small" style="opacity:.8"> · <span data-seat-total>₱0.00</span></span>
                    @endif
                </div>
                <button type="submit" class="btn btn--primary" data-seat-submit>Continue <span class="arrow">&rarr;</span></button>
            </div>
        </form>
    @else
        {{-- Step 2: lead contact + one attendee per seat --}}
        <form method="POST" action="{{ route('bookings.store', $screening) }}" class="fade-swap">
            @csrf
            @foreach ($selected as $seat)
                <input type="hidden" name="seat_ids[]" value="{{ $seat->seat_id }}">
            @endforeach

            <div class="alert alert--info">
                <div>
                    Seats <strong>{{ $selected->pluck('seat_label')->join(', ') }}</strong>
                    @if ($screening->isPaid())
                        · Amount due after submitting: <strong>₱{{ number_format($screening->price * $selected->count(), 2) }}</strong>
                    @endif
                    · <a href="{{ route('bookings.create', $screening) }}">change seats</a>
                </div>
            </div>

            <fieldset class="card reveal" style="margin-bottom:var(--s-5)">
                <legend>Person making the reservation</legend>
                <div class="form-grid">
                    <div class="field @error('lead_first_name') has-error @enderror">
                        <label for="lead_first_name">First name <span class="req">*</span></label>
                        <input type="text" id="lead_first_name" name="lead_first_name" value="{{ old('lead_first_name') }}" maxlength="50" required autocomplete="given-name">
                        @error('lead_first_name') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="lead_middle_name">Middle name</label>
                        <input type="text" id="lead_middle_name" name="lead_middle_name" value="{{ old('lead_middle_name') }}" maxlength="50" autocomplete="additional-name">
                    </div>
                    <div class="field @error('lead_last_name') has-error @enderror">
                        <label for="lead_last_name">Last name <span class="req">*</span></label>
                        <input type="text" id="lead_last_name" name="lead_last_name" value="{{ old('lead_last_name') }}" maxlength="50" required autocomplete="family-name">
                        @error('lead_last_name') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field @error('lead_contact_no') has-error @enderror">
                        <label for="lead_contact_no">Contact no. <span class="req">*</span></label>
                        <input type="text" id="lead_contact_no" name="lead_contact_no" value="{{ old('lead_contact_no') }}" maxlength="20" required autocomplete="tel" inputmode="tel" placeholder="09XX XXX XXXX">
                        @error('lead_contact_no') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field @error('lead_email') has-error @enderror">
                        <label for="lead_email">Email</label>
                        <input type="email" id="lead_email" name="lead_email" value="{{ old('lead_email') }}" maxlength="100" autocomplete="email">
                        @error('lead_email') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="lead_seat_id">Are you one of the attendees?</label>
                        <select id="lead_seat_id" name="lead_seat_id">
                            <option value="">No, I'm booking for others</option>
                            @foreach ($selected as $seat)
                                <option value="{{ $seat->seat_id }}" @selected(old('lead_seat_id') == $seat->seat_id)>Yes — I'll sit in {{ $seat->seat_label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>

            @foreach ($selected as $seat)
                @php($p = 'attendees.'.$seat->seat_id.'.')
                @php($n = 'attendees['.$seat->seat_id.']')
                @php($id = 'a'.$seat->seat_id.'_')
                <fieldset class="card reveal" style="margin-bottom:var(--s-5)">
                    <legend><span class="badge badge--gold badge--plain" style="margin-right:.5rem">{{ $seat->seat_label }}</span>Attendee for seat {{ $seat->seat_label }}</legend>
                    <div class="form-grid">
                        <div class="field @error($p.'first_name') has-error @enderror">
                            <label for="{{ $id }}first">First name <span class="req">*</span></label>
                            <input type="text" id="{{ $id }}first" name="{{ $n }}[first_name]" value="{{ old($p.'first_name') }}" maxlength="50" required>
                            @error($p.'first_name') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="{{ $id }}middle">Middle name</label>
                            <input type="text" id="{{ $id }}middle" name="{{ $n }}[middle_name]" value="{{ old($p.'middle_name') }}" maxlength="50">
                        </div>
                        <div class="field @error($p.'last_name') has-error @enderror">
                            <label for="{{ $id }}last">Last name <span class="req">*</span></label>
                            <input type="text" id="{{ $id }}last" name="{{ $n }}[last_name]" value="{{ old($p.'last_name') }}" maxlength="50" required>
                            @error($p.'last_name') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field @error($p.'age') has-error @enderror">
                            <label for="{{ $id }}age">Age</label>
                            <input type="number" id="{{ $id }}age" name="{{ $n }}[age]" value="{{ old($p.'age') }}" min="0" max="255">
                            @error($p.'age') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="{{ $id }}sex">Sex</label>
                            <select id="{{ $id }}sex" name="{{ $n }}[sex]">
                                <option value="">—</option>
                                <option value="M" @selected(old($p.'sex') === 'M')>M</option>
                                <option value="F" @selected(old($p.'sex') === 'F')>F</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="{{ $id }}company">Company / School</label>
                            <input type="text" id="{{ $id }}company" name="{{ $n }}[company_school]" value="{{ old($p.'company_school') }}" maxlength="150">
                        </div>
                        <div class="field">
                            <label for="{{ $id }}contact">Contact no.</label>
                            <input type="text" id="{{ $id }}contact" name="{{ $n }}[contact_no]" value="{{ old($p.'contact_no') }}" maxlength="20" inputmode="tel">
                        </div>
                        <div class="field @error($p.'email') has-error @enderror">
                            <label for="{{ $id }}email">Email</label>
                            <input type="email" id="{{ $id }}email" name="{{ $n }}[email]" value="{{ old($p.'email') }}" maxlength="100">
                            @error($p.'email') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="{{ $id }}senior">Senior citizen card no.</label>
                            <input type="text" id="{{ $id }}senior" name="{{ $n }}[senior_card_no]" value="{{ old($p.'senior_card_no') }}" maxlength="30">
                        </div>
                    </div>
                    <input type="hidden" name="{{ $n }}[pwd_indicator]" value="0">
                    <label class="check">
                        <input type="checkbox" name="{{ $n }}[pwd_indicator]" value="1" @checked(old($p.'pwd_indicator'))>
                        Person with disability (PWD)
                    </label>
                </fieldset>
            @endforeach

            <div class="summary-bar">
                <div>
                    <strong>{{ $selected->count() }} {{ Str::plural('seat', $selected->count()) }}</strong>
                    <span class="small" style="opacity:.8"> · {{ $selected->pluck('seat_label')->join(', ') }}
                        @if ($screening->isPaid()) · ₱{{ number_format($screening->price * $selected->count(), 2) }} @endif
                    </span>
                </div>
                <button type="submit" class="btn btn--primary">Submit reservation <span class="arrow">&rarr;</span></button>
            </div>
        </form>
    @endif
@endsection
