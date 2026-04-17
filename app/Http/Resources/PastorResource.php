<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PastorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'fullname'    => $this->fullname,
            'photo'       => $this->photo ? env('APP_URL') . '/' . ltrim($this->photo, '/') : null,
            'started_at'  => $this->started_at,
            'ended_at'    => $this->ended_at,
            'description' => $this->description,

            // timestamps
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
