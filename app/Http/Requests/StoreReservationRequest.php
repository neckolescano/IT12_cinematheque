<?php

namespace App\Http\Requests;

use App\Models\ReservationAttendee;
use App\Models\Screening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public reservation submission.
 *
 * Form shape:
 *   seat_ids[]                      selected seat ids
 *   lead_*                          booker contact (reservations table)
 *   lead_seat_id                    optional: which seat the booker occupies
 *   attendees[<seat_id>][field]     exactly one attendee per selected seat
 */
class StoreReservationRequest extends FormRequest
{
    public const MAX_SEATS_PER_RESERVATION = 10;

    public function authorize(): bool
    {
        return true; // public route — moviegoers never log in
    }

    public function rules(): array
    {
        return [
            'seat_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_SEATS_PER_RESERVATION],
            'seat_ids.*' => ['required', 'integer', 'distinct', Rule::exists('seats', 'seat_id')],

            'lead_first_name' => ['required', 'string', 'max:50'],
            'lead_middle_name' => ['nullable', 'string', 'max:50'],
            'lead_last_name' => ['required', 'string', 'max:50'],
            'lead_contact_no' => ['required', 'string', 'max:20'],
            'lead_email' => ['nullable', 'email', 'max:100'],
            'lead_seat_id' => ['nullable', 'integer', Rule::in($this->input('seat_ids', []))],

            'attendees' => ['required', 'array'],
            'attendees.*.first_name' => ['required', 'string', 'max:50'],
            'attendees.*.middle_name' => ['nullable', 'string', 'max:50'],
            'attendees.*.last_name' => ['required', 'string', 'max:50'],
            'attendees.*.age' => ['nullable', 'integer', 'min:0', 'max:255'],
            'attendees.*.sex' => ['nullable', Rule::in(ReservationAttendee::SEXES)],
            'attendees.*.company_school' => ['nullable', 'string', 'max:150'],
            'attendees.*.contact_no' => ['nullable', 'string', 'max:20'],
            'attendees.*.email' => ['nullable', 'email', 'max:100'],
            'attendees.*.senior_card_no' => ['nullable', 'string', 'max:30'],
            'attendees.*.pwd_indicator' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'seat_ids' => 'seats',
            'attendees.*.first_name' => 'attendee first name',
            'attendees.*.last_name' => 'attendee last name',
            'attendees.*.email' => 'attendee email',
            'attendees.*.age' => 'attendee age',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Screening $screening */
                $screening = $this->route('screening');
                $seatIds = array_map('intval', $this->input('seat_ids'));

                if ($screening->event_date->lt(today())) {
                    $validator->errors()->add('seat_ids', 'This screening has already taken place.');

                    return;
                }

                // Business rule 10: one declared attendee per reserved seat, no more, no less.
                $attendeeSeatIds = array_map('intval', array_keys($this->input('attendees')));
                sort($attendeeSeatIds);
                $sortedSeatIds = $seatIds;
                sort($sortedSeatIds);
                if ($attendeeSeatIds !== $sortedSeatIds) {
                    $validator->errors()->add('attendees', 'Every selected seat needs exactly one attendee.');
                }

                $taken = array_intersect($seatIds, $screening->takenSeatIds());
                if ($taken) {
                    $validator->errors()->add('seat_ids', 'Some selected seats are already reserved. Please choose again.');
                }

                if (count($seatIds) > $screening->availableSeatCount()) {
                    $validator->errors()->add('seat_ids', 'Not enough seats left for this screening.');
                }
            },
        ];
    }
}
