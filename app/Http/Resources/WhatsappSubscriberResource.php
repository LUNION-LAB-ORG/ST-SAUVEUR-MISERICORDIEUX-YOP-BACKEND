<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappSubscriberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'phone'           => $this->phone,
            'lists'           => $this->lists ?? [],
            'source'          => $this->source,
            'consented_at'    => optional($this->consented_at)->toDateTimeString(),
            'unsubscribed_at' => optional($this->unsubscribed_at)->toDateTimeString(),
            'created_at'      => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
