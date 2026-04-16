<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'author'        => $this->author,
            'category'      => $this->category,
            'image'         => $this->image ? env('APP_URL') . '/' . $this->image : null,
            'new_resume'    => $this->new_resume,
            'location'      => $this->location,
            'content'       => $this->content,
            'status'        => $this->new_status, // draft | published
            'views'         => $this->views ?? 0,
            'published_at'  => optional($this->published_at)->toDateString(),

            // Timestamps
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
