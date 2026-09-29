<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'fullname'        => $this->fullname,
            'function'        => $this->function,
            'missions'        => $this->missions,
            'biography'       => $this->biography,
            'ordination_year' => $this->ordination_year !== null ? (int) $this->ordination_year : null,
            'photo'           => MediaUrl::absolute($this->photo),
            'status'          => $this->status,
            'sort_order'      => (int) $this->sort_order,
        ];
    }
}
