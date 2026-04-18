<?php

namespace App\Http\Requests\User;

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
            'fullname'           => 'required|string|max:255',
            'email'              => 'nullable|email|max:100|unique:users,email,' . $this->id,
            'phone'              => 'required|string|max:100|unique:users,phone,' . $this->id,
            'password'           => ($this->id ? 'nullable' : 'required') . '|string|min:6',
            'status'             => 'sometimes|in:active,inactive',
            // Accepte aussi photo en upload (handled in controller)
            'photo'              => 'nullable',
            'role'               => 'nullable|in:admin,priest',
            'email_verified_at'  => 'nullable|date',
        ];
    }
}
