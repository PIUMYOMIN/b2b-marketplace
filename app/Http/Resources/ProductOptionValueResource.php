<?php
// ============================================================================
// FILE: app/Http/Resources/ProductOptionValueResource.php
// ============================================================================
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductOptionValueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'label'    => $this->label,
            'value'    => $this->value,
            'meta'     => $this->meta,        // hex colour, image URL, etc.
            'position' => $this->position,
        ];
    }
}
