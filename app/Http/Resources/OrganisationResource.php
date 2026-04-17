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
            'title'                   => $this->title,
            'location_at'             => $this->location_at,
            'image'                   => $this->image ? env('APP_URL') . '/' . $this->image : null,
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
            'is_paid'                 => (bool) $this->is_paid,
            'price'                   => $this->price,
            'pricing_tiers'           => $this->pricing_tiers,
            'max_participants'        => $this->max_participants,
            'registration_deadline'   => $this->registration_deadline,
            'converted_event_id'      => $this->converted_event_id,
            'created_at'              => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
