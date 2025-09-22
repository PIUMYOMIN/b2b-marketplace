<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'phone' => $this->phone,
        'type' => $this->type,
        'user_id' => $this->type,
        'is_active' => $this->is_active,
        'address' => $this->address,
        'city' => $this->city,
        'state' => $this->state,
        'roles' => $this->roles->pluck('name'),
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
}