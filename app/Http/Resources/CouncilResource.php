<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouncilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'role'         => $this->role,
            'leader_title' => $this->leader_title,
            'leader_name'  => $this->leader_name,
            'status'       => $this->status,
            'sort_order'   => (int) $this->sort_order,
        ];
    }
}
