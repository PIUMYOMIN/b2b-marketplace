<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'aspect_ratings' => $this->aspect_ratings,
            'buyer' => $this->whenLoaded('buyer', function() {
                return [
                    'name' => $this->buyer->name,
                    'company' => $this->buyer->company_name
                ];
            }),
            'created_at' => $this->created_at->format('M d, Y')
        ];
    }
}