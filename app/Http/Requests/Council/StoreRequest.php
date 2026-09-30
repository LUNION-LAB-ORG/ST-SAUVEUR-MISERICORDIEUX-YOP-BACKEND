<?php

namespace App\Http\Requests\Council;

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
            'name'              => 'required|string|max:150',
            'role'              => 'nullable|string|max:255',
            'leader_title'      => 'nullable|string|max:100',
            'leader_name'       => 'nullable|string|max:150',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
            'members'           => 'sometimes|array|max:100',
            'members.*.name'     => 'required|string|max:150',
            'members.*.function' => 'nullable|string|max:150',
            'members.*.phone'    => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .\-]{6,30}$/'],
        ];
    }
}
