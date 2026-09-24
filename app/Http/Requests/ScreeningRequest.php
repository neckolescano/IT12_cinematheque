<?php

namespace App\Http\Requests;

use App\Models\Screening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $screening = $this->route('screening');

        return $screening
            ? $this->user()->can('update', $screening)
            : $this->user()->can('create', Screening::class);
    }

    public function rules(): array
    {
        return [
            'event_title' => ['required', 'string', 'max:150'],
            'movie_id' => ['nullable', 'integer', Rule::exists('movies', 'movie_id')],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(Screening::TYPES)],
            'price' => ['nullable', 'required_if:type,paid', 'numeric', 'min:0', 'max:999999.99'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Screening|null $screening */
                $screening = $this->route('screening');
                if ($screening && (int) $this->input('total_seats') < $screening->reservationSeats()->count()) {
                    $validator->errors()->add('total_seats', 'Total seats cannot be lower than the seats already reserved.');
                }
            },
        ];
    }

    /** Free screenings carry no price. */
    public function screeningData(): array
    {
        $data = $this->validated();
        if ($data['type'] === 'free') {
            $data['price'] = null;
        }

        return $data;
    }
}
