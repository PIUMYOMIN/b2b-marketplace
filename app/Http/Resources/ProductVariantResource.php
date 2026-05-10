<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'sku'           => $this->sku,
            'price'         => $this->price,
            'quantity'      => $this->quantity,
            'quantity_unit' => $this->effectiveUnit(),
            'moq'           => $this->effectiveMoq(),
            'quantity_step' => $this->effectiveMoq(),   // step always equals MOQ
            'image'         => $this->image,
            'label'         => $this->label(),   // e.g. "Red / M"
            'position'      => $this->position,
            'is_active'     => $this->is_active,
            'in_stock'      => $this->isInStock(),
            // Each selected option value with its option name
            'option_values' => $this->whenLoaded(
                'optionValues',
                fn() =>
                $this->optionValues->map(fn($v) => [
                    'option_id'    => $v->option->id,
                    'option_name'  => $v->option->name,
                    'option_type'  => $v->option->type,
                    'value_id'     => $v->id,
                    'label'        => $v->label,
                    'value'        => $v->value,
                    'meta'         => $v->meta,
                ])
            ),
        ];
    }
}