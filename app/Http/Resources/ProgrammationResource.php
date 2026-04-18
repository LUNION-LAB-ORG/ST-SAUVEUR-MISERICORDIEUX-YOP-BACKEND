<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'image'        => $this->image
                ? env('APP_URL') . '/' . ltrim($this->image, '/')
                : null,
            'category'     => $this->category,
            'date_at'      => $this->date_at,
            'started_at'   => $this->started_at,
            'ended_at'     => $this->ended_at,
            'description'  => $this->description,
            'location'     => $this->location,
            'is_published' => (bool) ($this->is_published ?? true),
            'created_at'   => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
