<?php

namespace App\Http\Requests\Mediation;

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
            'title'            => 'required|string|max:255',
            'date_at'          => 'required|date',
            'author'           => 'required|string|max:255',
            'category'         => 'required|string|max:255',
            'mediation_status' => 'required|in:draft,published,archived',
            'image'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'content'          => 'nullable|string',
        ];
    }
}
