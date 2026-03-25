<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,  // FIX: was $this->type (returned role string instead of ID)
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'status' => $this->status,
            'is_active' => $this->is_active,
            // Profile fields — all exist in the users table
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'date_of_birth' => $this->date_of_birth,
            'profile_photo' => $this->profile_photo
                ? (str_starts_with($this->profile_photo, 'http')
                    ? $this->profile_photo
                    : url('storage/' . ltrim($this->profile_photo, '/')))
                : null,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name'), []),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
