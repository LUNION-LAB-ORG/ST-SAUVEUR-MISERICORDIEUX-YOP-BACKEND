<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'date'         => $this->date ? substr($this->date, 0, 10) : null,
            'start_time'   => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'label'        => $this->label,
            'location'     => $this->location,
            'is_cancelled' => (bool) $this->is_cancelled,
            'time_slot_id' => $this->time_slot_id,
            'created_at'   => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
