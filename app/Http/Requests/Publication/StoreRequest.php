<?php

namespace App\Http\Requests\Publication;

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
            'slug'              => 'nullable|string|max:180|alpha_dash|unique:publications,slug',
            'type'              => 'required|string|in:photo,video,text',
            'format'            => 'nullable|string|max:100',
            'category'          => 'nullable|string|max:100',
            'title'             => 'required|string|max:255',
            'lead'              => 'nullable|string',
            'body'              => 'nullable|string',
            'quote'             => 'nullable|string',
            'cover'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'video_url'         => 'nullable|url|max:255',
            'video_duration'    => 'nullable|string|max:20',
            'author_label'      => 'sometimes|string|max:255',
            'is_featured'       => 'sometimes|boolean',
            'published_at'      => 'sometimes|nullable|date',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
