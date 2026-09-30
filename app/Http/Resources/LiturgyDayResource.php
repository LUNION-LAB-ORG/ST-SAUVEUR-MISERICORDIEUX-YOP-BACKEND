<?php

namespace App\Http\Resources;

use App\Models\Homily;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiturgyDayResource extends JsonResource
{
    /** Vrai quand la journée renvoyée est une journée antérieure (AELF indisponible). */
    public bool $isFallback = false;

    public function fallback(bool $value = true): static
    {
        $this->isFallback = $value;
        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'date'           => substr($this->date, 0, 10),
            'feast'          => $this->displayFeast(),
            'degree'         => $this->degree,
            'color'          => $this->displayColor(),
            'is_fallback'    => $this->isFallback,
            'imported_at'    => optional($this->imported_at)->format('Y-m-d H:i:s'),
            'readings'       => $this->readings ?? [],
            'homily'         => $this->homilyPayload(),

            // Informations complémentaires (écran d'administration)
            'zone'           => $this->zone,
            'feast_override' => $this->feast_override,
            'color_override' => $this->color_override,
        ];
    }

    /** Homélie publiée à cette date, sinon null. */
    private function homilyPayload(): ?array
    {
        $homily = Homily::query()
            ->visible()
            ->where('date', substr($this->date, 0, 10))
            ->with('priest')
            ->orderByDesc('id')
            ->first();

        if (!$homily) {
            return null;
        }

        return [
            'id'        => $homily->id,
            'title'     => $homily->title,
            'content'   => $homily->content,
            'audio_url' => \App\Support\MediaUrl::absolute($homily->audio_url),
            'author'    => $homily->priest ? [
                'fullname' => $homily->priest->fullname,
                'function' => $homily->priest->function,
            ] : null,
        ];
    }
}
