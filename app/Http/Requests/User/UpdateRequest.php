<?php

namespace App\Http\Requests\User;

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
            'fullname'           => 'sometimes|string|max:255',
            'email'              => 'sometimes|nullable|email|max:100|unique:users,email,' . $this->id,
            'phone'              => 'sometimes|string|max:100|unique:users,phone,' . $this->id,
            'password'           => 'sometimes|nullable|string|min:6',
            'status'             => 'sometimes|in:active,inactive',
            'photo'              => 'sometimes|nullable',
            'role'               => 'sometimes|nullable|in:admin,priest',
            'email_verified_at'  => 'sometimes|nullable|date',
        ];
    }
}
