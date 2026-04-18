<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantEventResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'fullname'   => $this->fullname,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'message'    => $this->message,

            'event'      => new EventResource($this->whenLoaded('event')),

            'payment_status'    => $this->payment_status ?? 'free',
            'wave_checkout_id'  => $this->wave_checkout_id,
            'payment_reference' => $this->payment_reference,
            'amount'            => $this->amount,
            'tier_label'        => $this->tier_label,

            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
