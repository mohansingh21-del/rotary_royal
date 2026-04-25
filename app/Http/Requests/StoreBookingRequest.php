<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize()
    {
        return true; // We handles logic within controller/service
    }

    public function rules()
    {
        $assetId = $this->input('asset_id');
        $asset = \App\Models\Asset::find($assetId);
        
        $paymentRule = ($asset && $asset->price > 0) ? 'required' : 'nullable';

        return [
            'asset_id' => 'required|exists:assets,id',
            'payment_image' => $paymentRule . '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'user_name' => 'required|string|max:255',
            'user_phone' => 'required|regex:/^[0-9]{10}$/',
            'user_email' => 'required|email',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'id_number' => 'required|string',
            'id_image_path' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'reference' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'payment_image.required' => 'Payment image is required when the selected asset price is greater than 0.',
            'id_image_path.required' => 'ID image is required.',
        ];
    }
}
