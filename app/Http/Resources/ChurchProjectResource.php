<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChurchProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $collected = $this->collectedAmount();

        return [
            'title'             => $this->title,
            'presentation'      => $this->presentation,
            'goal_amount'       => (int) $this->goal_amount,
            'adjustment_amount' => (int) $this->adjustment_amount,
            'collected_amount'  => $collected,
            'progress'          => $this->progress($collected),
            'phases'            => array_values($this->phases ?? []),
            'gallery'           => array_values(array_map(
                fn ($path) => MediaUrl::absolute($path),
                $this->gallery ?? []
            )),
            'image'             => MediaUrl::absolute($this->image),
        ];
    }
}
