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
            'type'           => $this->type,
            'fullname'       => $this->fullname,
            'phone'          => $this->phone,
            'message'        => $this->message,
            'availability'   => $this->availability,
            'time_slot_id'   => $this->time_slot_id,
            'priest_id'      => $this->priest_id,
            'priest'         => $this->priest ? [
                'id'       => $this->priest->id,
                'fullname' => $this->priest->fullname,
                'function' => $this->priest->function,
            ] : null,
            'request_status' => $this->request_status,
            'assigned_priest_id' => $this->assigned_priest_id,
            'assigned_priest' => $this->assignedPriest ? [
                'id'       => $this->assignedPriest->id,
                'fullname' => $this->assignedPriest->fullname,
                'function' => $this->assignedPriest->function,
            ] : null,
            'proposed_at'    => optional($this->proposed_at)->toDateTimeString(),
            'listen_at'     => $this->listen_at,

            // Relations
            'time_slot'     => new TimeSlotResource($this->whenLoaded('timeSlot')),

            // Timestamps
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
