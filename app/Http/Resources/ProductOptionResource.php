<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'type'        => $this->type,        // color | size | text | image | input
            'position'    => $this->position,
            'is_required' => $this->is_required,
            'values'      => ProductOptionValueResource::collection(
                $this->whenLoaded('values')
            ),
        ];
    }
}
