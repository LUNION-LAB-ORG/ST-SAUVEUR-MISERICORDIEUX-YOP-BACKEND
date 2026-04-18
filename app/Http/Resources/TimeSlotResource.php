<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type ?? 'ecoute',
            'priest_id'    => $this->priest_id,
            'weekday'      => (int) $this->weekday,
            'start_time'   => $this->start_time,
            'end_time'     => $this->end_time,
            'capacity'     => $this->capacity,
            'notes'        => $this->notes,
            'is_available' => (bool) $this->is_available,

            'priest'       => $this->whenLoaded('priest'),

            'created_at'   => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
