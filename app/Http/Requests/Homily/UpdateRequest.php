<?php

namespace App\Http\Requests\Homily;

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
            'date'              => 'sometimes|date_format:Y-m-d',
            'priest_id'         => 'sometimes|nullable|integer|exists:priests,id',
            'title'             => 'sometimes|string|max:255',
            'content'           => 'sometimes|string',
            'audio_url'         => 'sometimes|nullable|url|max:255',
            'publish_at'        => 'sometimes|nullable|date',
            'notify_whatsapp'   => 'sometimes|boolean',
            'status'            => 'sometimes|string|in:draft,published,hidden',
        ];
    }
}
