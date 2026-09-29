<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'author'         => $this->author,
            'initial'        => mb_strtoupper(mb_substr(trim((string) $this->author), 0, 1)),
            'content'        => $this->content,
            'reply'          => $this->reply,
            'replied_at'     => optional($this->replied_at)->toDateTimeString(),
            'likes_count'    => (int) $this->likes_count,
            'created_at'     => optional($this->created_at)->toDateTimeString(),
            'status'         => $this->status,

            // Modération (admin)
            'publication_id' => $this->publication_id,
            'publication'    => $this->whenLoaded('publication', fn () => $this->publication ? [
                'id'    => $this->publication->id,
                'slug'  => $this->publication->slug,
                'title' => $this->publication->title,
            ] : null),
        ];
    }
}
