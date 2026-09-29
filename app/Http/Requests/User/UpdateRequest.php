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
            'email'              => 'sometimes|nullable|email|max:100|unique:users,email,' . ($this->route('user') ?? $this->id),
            'phone'              => 'sometimes|string|max:100|unique:users,phone,' . ($this->route('user') ?? $this->id),
            'password'           => 'sometimes|nullable|string|min:6',
            'status'             => 'sometimes|in:active,inactive,disabled',
            'photo'              => 'sometimes|nullable',
            'role'               => 'sometimes|nullable|in:admin,priest,secretariat,communication,treasurer,movement_leader',
            'service_id'         => 'sometimes|nullable|integer|exists:services,id',
            'email_verified_at'  => 'sometimes|nullable|date',
        ];
    }
}
