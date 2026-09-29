<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $gallery = array_values($this->gallery ?? []);

        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'type'            => $this->type,
            'format'          => $this->format,
            'category'        => $this->category,
            'title'           => $this->title,
            'lead'            => $this->lead,
            'body'            => $this->body,
            'quote'           => $this->quote,
            'cover'           => MediaUrl::absolute($this->cover),
            'gallery'         => array_map(fn ($path) => MediaUrl::absolute($path), $gallery),
            'video_url'       => $this->video_url,
            'youtube_id'      => $this->youtubeId(),
            'video_duration'  => $this->video_duration,
            'author_label'    => $this->author_label,
            'is_featured'     => (bool) $this->is_featured,
            'likes_count'     => (int) $this->likes_count,
            'allow_comments'  => (bool) ($this->allow_comments ?? true),
            'show_likes'      => (bool) ($this->show_likes ?? true),
            'comments_count'  => (int) ($this->published_comments_count
                ?? $this->comments()->where('status', 'published')->count()),
            'photos_count'    => count($gallery),
            'reading_minutes' => $this->readingMinutes(),
            'published_at'    => optional($this->published_at)->toDateTimeString(),
            'status'          => $this->status,
            'sort_order'      => (int) $this->sort_order,
        ];
    }
}
