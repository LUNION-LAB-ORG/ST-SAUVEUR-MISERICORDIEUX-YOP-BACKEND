<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'fullname'      => $this->fullname,
            'phone'         => $this->phone,
            'message'       => $this->message,
            'time_slot_id'  => $this->time_slot_id,
            'request_status' => $this->request_status,
            'listen_at'     => $this->listen_at,

            // Relations
            'time_slot'     => new TimeSlotResource($this->whenLoaded('timeSlot')),

            // Timestamps
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
