<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoryMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'year'       => $this->year,
            'title'      => $this->title,
            'status'     => $this->status,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
