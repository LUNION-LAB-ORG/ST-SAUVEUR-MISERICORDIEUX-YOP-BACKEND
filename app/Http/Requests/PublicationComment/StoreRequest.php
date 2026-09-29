<?php

namespace App\Http\Requests\PublicationComment;

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
            'author'            => 'required|string|max:80',
            'content'           => 'required|string|max:1000',
        ];
    }
}
