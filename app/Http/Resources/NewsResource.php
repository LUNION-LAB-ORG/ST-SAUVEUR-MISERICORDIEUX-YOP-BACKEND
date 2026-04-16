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
            'status'        => $this->new_status, // draft | published
            'views'         => $this->views ?? 0,
            'published_at'  => optional($this->published_at)->toDateString(),

            // Timestamps
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
