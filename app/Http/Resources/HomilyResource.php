<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $priest = $this->priest;

        return [
            'id'        => $this->id,
            'date'      => $this->date,
            'title'     => $this->title,
            'content'   => $this->content,
            'audio_url' => \App\Support\MediaUrl::absolute($this->audio_url),
            'status'    => $this->status,
            'state'     => $this->state(),
            'publish_at'      => optional($this->publish_at)->toDateTimeString(),
            'notify_whatsapp' => (bool) $this->notify_whatsapp,
            'priest'    => $priest ? [
                'id'       => $priest->id,
                'fullname' => $priest->fullname,
                'function' => $priest->function,
            ] : null,
        ];
    }
}
