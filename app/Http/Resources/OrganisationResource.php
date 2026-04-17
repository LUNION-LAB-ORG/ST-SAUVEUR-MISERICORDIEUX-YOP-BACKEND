<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'isParishMember'          => $this->is_parish_member,
            'movement'                => $this->movement,
            'email'                   => $this->email,
            'eventType'               => $this->event_type,
            'date'                    => optional($this->date)->toDateString(),
            'startTime'               => $this->start_time,
            'endTime'                 => $this->end_time,
            'description'             => $this->description,
            'estimatedParticipants'   => $this->estimated_participants,
            'request_status'          => $this->request_status,
            'created_at'              => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
