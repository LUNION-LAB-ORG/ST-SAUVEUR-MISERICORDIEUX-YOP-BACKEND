<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'icon'         => $this->icon,
            'title'        => $this->title,
            'message'      => $this->message,
            'related_type' => $this->related_type,
            'related_id'   => $this->related_id,
            'link'         => $this->link,
            'is_read'      => (bool) $this->is_read,
            'read_at'      => optional($this->read_at)->toDateTimeString(),
            'created_at'   => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
