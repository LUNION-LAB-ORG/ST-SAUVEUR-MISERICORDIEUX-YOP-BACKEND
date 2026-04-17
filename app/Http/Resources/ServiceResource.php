<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'image'       => $this->image ? env('APP_URL') . '/' . ltrim($this->image, '/') : null,
            'content'     => $this->content,
            'leader'      => $this->leader,
            'schedule'    => $this->schedule,
            'created_at'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
