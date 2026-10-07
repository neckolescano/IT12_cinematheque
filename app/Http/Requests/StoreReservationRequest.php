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
 * Form shape (the "Who's coming?" form, as in the mobile app):
 *   seat_ids[]                      selected seat ids, in the order they were chosen
 *   attendees[<seat_id>][field]     exactly one attendee per selected seat
 * The FIRST seat is the primary booker: when lead_* fields are not sent, they are taken from
 * that seat's attendee (name, mobile, email) and lead_seat_id is that seat. Older callers may
 * still send lead_* explicitly.
 * Mobile numbers: the form sends the 9 digits after "+639"; full 09XXXXXXXXX / +639XXXXXXXXX
 * numbers are still accepted. Everything is stored as typed after normalising.
 */
class StoreReservationRequest extends FormRequest
{
    public const MAX_SEATS_PER_RESERVATION = 10;

    /** Philippine mobile number: 09XXXXXXXXX (11 digits) or +639XXXXXXXXX. Checked after spaces/dashes are removed. */
    public const PH_MOBILE = '/^(09\d{9}|\+639\d{9})$/';

    public const PH_MOBILE_MESSAGE = 'Enter the 9 digits of the mobile number after +639.';

    public function authorize(): bool
    {
        return true; // public route — moviegoers never log in
    }

    /**
     * "0917 123-4567" → "09171234567"; the form's 9 digits after the fixed "+639" prefix
     * ("171234567") → "+639171234567". Then the booker defaults to the first seat's attendee.
     */
    protected function prepareForValidation(): void
    {
        $clean = function ($v) {
            if (! is_string($v)) {
                return $v;
            }
            $v = preg_replace('/[\s\-().]/', '', $v);

            return preg_match('/^\d{9}$/', $v) ? '+639'.$v : $v;
        };

        $attendees = $this->input('attendees');
        if (is_array($attendees)) {
            foreach ($attendees as $seatId => $attendee) {
                if (is_array($attendee) && isset($attendee['contact_no'])) {
                    $attendees[$seatId]['contact_no'] = $clean($attendee['contact_no']);
                }
            }
        }

        $this->merge(array_filter([
            'lead_contact_no' => $clean($this->input('lead_contact_no')),
            'attendees' => $attendees,
        ], fn ($v) => $v !== null));

        // The first chosen seat is the primary booker (no separate "Your details").
        $seatIds = (array) $this->input('seat_ids', []);
        $first = is_array($attendees) && $seatIds ? ($attendees[reset($seatIds)] ?? null) : null;
        if (is_array($first) && ! $this->hasAny(['lead_first_name', 'lead_last_name', 'lead_email', 'lead_contact_no'])) {
            $this->merge([
                'lead_first_name' => $first['first_name'] ?? null,
                'lead_middle_name' => $first['middle_name'] ?? null,
                'lead_last_name' => $first['last_name'] ?? null,
                'lead_contact_no' => $first['contact_no'] ?? null,
                'lead_email' => $first['email'] ?? null,
                'lead_seat_id' => reset($seatIds),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'lead_contact_no.regex' => self::PH_MOBILE_MESSAGE,
            'attendees.*.contact_no.regex' => self::PH_MOBILE_MESSAGE,
        ];
    }

    public function rules(): array
    {
        return [
            'seat_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_SEATS_PER_RESERVATION],
            'seat_ids.*' => ['required', 'integer', 'distinct', Rule::exists('seats', 'seat_id')],

            'lead_first_name' => ['required', 'string', 'max:50'],
            'lead_middle_name' => ['nullable', 'string', 'max:50'],
            'lead_last_name' => ['required', 'string', 'max:50'],
            'lead_contact_no' => ['required', 'string', 'max:20', 'regex:'.self::PH_MOBILE],
            // Required: the pending notice and the e-ticket are emailed here.
            'lead_email' => ['required', 'email', 'max:100'],
            'lead_seat_id' => ['nullable', 'integer', Rule::in($this->input('seat_ids', []))],

            'attendees' => ['required', 'array'],
            'attendees.*.first_name' => ['required', 'string', 'max:50'],
            'attendees.*.middle_name' => ['nullable', 'string', 'max:50'],
            'attendees.*.last_name' => ['required', 'string', 'max:50'],
            // Every attendee detail is required except middle name, senior card no. and PWD ID no.
            'attendees.*.age' => ['required', 'integer', 'min:1', 'max:120'],
            'attendees.*.sex' => ['required', Rule::in(ReservationAttendee::SEXES)],
            'attendees.*.company_school' => ['required', 'string', 'max:150'],
            'attendees.*.contact_no' => ['required', 'string', 'max:20', 'regex:'.self::PH_MOBILE],
            'attendees.*.email' => ['required', 'email', 'max:100'],
            'attendees.*.senior_card_no' => ['nullable', 'string', 'max:30'],
            'attendees.*.pwd_id_no' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return [
            'seat_ids' => 'seats',
            'lead_first_name' => 'first name',
            'lead_middle_name' => 'middle name',
            'lead_last_name' => 'last name',
            'lead_contact_no' => 'contact number',
            'lead_email' => 'email',
            'lead_seat_id' => 'your seat',
            'attendees.*.first_name' => 'attendee first name',
            'attendees.*.last_name' => 'attendee last name',
            'attendees.*.email' => 'attendee email',
            'attendees.*.age' => 'attendee age',
            'attendees.*.sex' => 'attendee sex',
            'attendees.*.company_school' => 'company or school',
            'attendees.*.contact_no' => 'attendee contact number',
            'attendees.*.pwd_id_no' => 'PWD ID number',
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
