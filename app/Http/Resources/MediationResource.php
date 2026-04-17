<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'date_at'     => $this->date_at,
            'author'      => $this->author,
            'category'    => $this->category,
            'image'       => $this->image
                ? env('APP_URL') . '/' . ltrim($this->image, '/')
                : null,
            'content'     => $this->content,
            'status'      => $this->mediation_status,
            'views'       => $this->views,
            'created_at'  => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
