<?php

namespace App\Http\Requests\Publication;

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
            'slug'              => ['sometimes', 'string', 'max:180', 'alpha_dash', \Illuminate\Validation\Rule::unique('publications', 'slug')->ignore($this->route('publication'))],
            'type'              => 'sometimes|string|in:photo,video,text',
            'format'            => 'sometimes|nullable|string|max:100',
            'category'          => 'sometimes|nullable|string|max:100',
            'title'             => 'sometimes|string|max:255',
            'lead'              => 'sometimes|nullable|string',
            'body'              => 'sometimes|nullable|string',
            'quote'             => 'sometimes|nullable|string',
            'cover'             => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'video_url'         => 'sometimes|nullable|url|max:255',
            'video_duration'    => 'sometimes|nullable|string|max:20',
            'author_label'      => 'sometimes|string|max:255',
            'is_featured'       => 'sometimes|boolean',
            'allow_comments'    => 'sometimes|boolean',
            'show_likes'        => 'sometimes|boolean',
            'published_at'      => 'sometimes|nullable|date',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
