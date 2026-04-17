<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'fullname'         => $this->fullname,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'status'           => $this->status,
            'role'             => $this->role,
            'photo'            => $this->photo ? env('APP_URL') . '/' . ltrim($this->photo, '/') : null,
            'email_verified_at' => $this->email_verified_at,

            // timestamps
            'created_at'    => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
