<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $priest = $this->priest;

        return [
            'id'        => $this->id,
            'date'      => $this->date,
            'title'     => $this->title,
            'content'   => $this->content,
            'audio_url' => $this->audio_url,
            'status'    => $this->status,
            'priest'    => $priest ? [
                'id'       => $priest->id,
                'fullname' => $priest->fullname,
                'function' => $priest->function,
            ] : null,
        ];
    }
}
