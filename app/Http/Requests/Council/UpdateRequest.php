<?php

namespace App\Http\Requests\Council;

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
            'name'              => 'sometimes|string|max:150',
            'role'              => 'sometimes|nullable|string|max:255',
            'leader_title'      => 'sometimes|nullable|string|max:100',
            'leader_name'       => 'sometimes|nullable|string|max:150',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
