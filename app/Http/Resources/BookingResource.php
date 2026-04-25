<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'assets_name' => $this->asset?->name,
            'assets_image' => $this->asset?->image ? asset($this->asset->image) : null,
            'assets_is_active' => $this->asset?->status,
            'price' => $this->asset?->price,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'user_phone' => $this->user_phone,
            'user_email' => $this->user_email,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'id_number' => $this->id_number,
            'id_image_path' => $this->id_image_path ? asset($this->id_image_path) : null,
            'payment_image' => $this->payment_image ? asset($this->payment_image) : null,
            'reference' => $this->reference,
            'referrer' => $this->whenLoaded('referrer', function() {
                return [
                    'id' => $this->referrer->id,
                    'name' => $this->referrer->name,
                    'phone' => $this->referrer->phone,
                ];
            }),
            'asset' => $this->whenLoaded('asset'),
            'user' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'phone' => $this->user->phone,
                ];
            }),
            'status' => $this->status,
            'buffer_time' => $this->buffer_time,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
