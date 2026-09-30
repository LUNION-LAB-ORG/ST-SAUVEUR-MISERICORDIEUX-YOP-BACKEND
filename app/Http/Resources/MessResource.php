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

            // Demande en ligne (sous-page /demande-messe)
            'number'           => $this->number,
            'intention_type'   => $this->intention_type,
            'for_whom'         => $this->for_whom,
            'is_confidential'  => (bool) $this->is_confidential,
            'formula'          => $this->formula ?? 'single',
            'masses_count'     => (int) ($this->masses_count ?? 1),
            'time_slot_id'     => $this->time_slot_id,
            'will_attend'      => (bool) $this->will_attend,
            'reminder'         => (bool) ($this->reminder ?? true),
            'payment_method'   => $this->payment_method,
            'needs_review'     => (bool) $this->needs_review,
            'time_slot'        => new TimeSlotResource($this->whenLoaded('timeSlot')),
            'schedules'        => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($s) => [
                'id'      => $s->id,
                'date'    => $s->date,
                'time'    => $s->hhmm(),
                'label'   => $s->label,
                'shifted' => (bool) $s->shifted,
            ])->values()),

            // timestamps
            'created_at'       => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
