<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                     => $this->id,
            'title'                  => $this->title,
            'description'            => $this->description,
            'date_at'                => $this->date_at,
            'time_at'                => $this->time_at,
            'image'                  => env('APP_URL') . '/' . $this->image,
            'location_at'            => $this->location_at,
            'is_paid'                => (bool) $this->is_paid,
            'price'                  => $this->price,
            'max_participants'       => $this->max_participants,
            'registration_deadline'  => optional($this->registration_deadline)->toDateTimeString(),
            'participants_count'     => $this->whenLoaded('participants', fn() => $this->participants->count(), $this->participants_count ?? null),
            'spots_remaining'        => $this->when(
                $this->max_participants !== null,
                fn() => max(0, $this->max_participants - ($this->participants_count ?? ($this->whenLoaded('participants') ? $this->participants->count() : 0)))
            ),

            'participants' => ParticipantEventResource::collection(
                $this->whenLoaded('participants')
            ),

            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
