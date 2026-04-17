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
            'image'                  => $this->image ? env('APP_URL') . '/' . $this->image : null,
            'location_at'            => $this->location_at,
            'is_paid'                => (bool) $this->is_paid,
            'price'                  => $this->price,
            'pricing_tiers'          => $this->computePricingTiersWithAvailability(),
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

    /**
     * Pour chaque tier, calcule spots_remaining = max_participants - participants inscrits à ce tier.
     * Retourne null si pricing_tiers est vide.
     */
    private function computePricingTiersWithAvailability(): ?array
    {
        $tiers = is_array($this->pricing_tiers) ? $this->pricing_tiers : null;
        if (!$tiers || count($tiers) === 0) return null;

        return array_map(function ($tier) {
            $label = $tier['label'] ?? '';
            $max   = $tier['max_participants'] ?? null;

            $data = [
                'label'       => $label,
                'amount'      => $tier['amount'] ?? 0,
                'description' => $tier['description'] ?? null,
                'max_participants' => $max,
            ];

            if ($max !== null && (int) $max > 0 && $label) {
                $taken = \App\Models\ParticipantEvent::where('event_id', $this->id)
                    ->where('tier_label', $label)
                    ->whereIn('payment_status', ['succeeded', 'pending', 'paid'])
                    ->count();
                $data['spots_remaining'] = max(0, (int) $max - $taken);
            }

            return $data;
        }, $tiers);
    }
}
