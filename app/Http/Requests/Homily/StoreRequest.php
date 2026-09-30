<?php

namespace App\Http\Requests\Homily;

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
            'date'              => 'required|date_format:Y-m-d',
            'priest_id'         => 'nullable|integer|exists:priests,id',
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'audio_url'         => 'nullable|url|max:255',
            'publish_at'        => 'nullable|date',
            'notify_whatsapp'   => 'sometimes|boolean',
            'status'            => 'sometimes|string|in:draft,published,hidden',
        ];
    }
}
