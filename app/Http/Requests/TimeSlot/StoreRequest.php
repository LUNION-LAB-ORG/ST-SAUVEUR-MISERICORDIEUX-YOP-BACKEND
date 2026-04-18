<?php

namespace App\Http\Requests\TimeSlot;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => 'required|string|in:messe,ecoute,confession,adoration,autre',
            'priest_id'   => 'nullable|integer|exists:users,id',
            'weekday'     => 'required|integer|min:0|max:6',
            'start_time'  => 'required|string',
            'end_time'    => 'required|string',
            'capacity'    => 'nullable|integer|min:1',
            'notes'       => 'nullable|string|max:255',
            'is_available'=> 'nullable|boolean',
        ];
    }
}
