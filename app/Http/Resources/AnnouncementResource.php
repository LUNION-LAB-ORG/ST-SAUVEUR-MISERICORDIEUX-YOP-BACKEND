<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'category'      => $this->category,
            'title'         => $this->title,
            'content'       => $this->content,
            'contact'       => $this->contact,
            'is_featured'   => (bool) $this->is_featured,
            'visible_from'  => $this->visible_from ? substr($this->visible_from, 0, 10) : null,
            'visible_until' => $this->visible_until ? substr($this->visible_until, 0, 10) : null,
            'status'        => $this->status,
            'sort_order'    => (int) $this->sort_order,
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
