<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'fullname'         => $this->fullname,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'message'          => $this->message,
            'request_status'   => $this->request_status,
            'payment_status'   => $this->payment_status,
            'wave_reference'   => $this->wave_reference,
            'wave_checkout_id' => $this->wave_checkout_id,
            'amount'           => $this->amount,
            'date_at'          => $this->date_at,
            'time_at'          => $this->time_at,

            // timestamps
            'created_at'       => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
