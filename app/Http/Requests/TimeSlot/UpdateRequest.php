<?php

namespace App\Http\Requests\TimeSlot;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => 'sometimes|string|in:messe,ecoute,confession,adoration,autre',
            'priest_id'   => 'sometimes|nullable|integer|exists:users,id',
            'weekday'     => 'sometimes|integer|min:0|max:6',
            'start_time'  => 'sometimes|string',
            'end_time'    => 'sometimes|string',
            'capacity'    => 'sometimes|nullable|integer|min:1',
            'notes'       => 'sometimes|nullable|string|max:255',
            'is_available'=> 'sometimes|nullable|boolean',
        ];
    }
}
