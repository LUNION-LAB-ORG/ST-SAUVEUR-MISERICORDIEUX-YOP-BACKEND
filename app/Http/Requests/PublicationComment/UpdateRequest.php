<?php

namespace App\Http\Requests\PublicationComment;

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
            'status'            => 'sometimes|string|in:pending,published,rejected',
            'reply'             => 'sometimes|nullable|string|max:2000',
        ];
    }
}
