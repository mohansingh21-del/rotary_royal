<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponse;

    /**
     * Get a setting by its key.
     */
    public function getSetting(string $key)
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->errorResponse('Setting not found', 404);
        }

        return $this->successResponse($setting->value, "Setting for $key retrieved successfully");
    }

    /**
     * Update or create a setting for bank details.
     */
    public function updateBankDetails(Request $request)
    {
        $request->validate([
            'accountName' => 'required|string',
            'accountNumber' => 'required|string',
            'ifscCode' => 'required|string',
            'bankName' => 'required|string',
            'qrCode' => 'nullable|string', // Expecting URL or path
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => 'bank_details'],
            ['value' => $request->all()]
        );

        return $this->successResponse($setting->value, 'Bank details updated successfully');
    }

    /**
     * Update or create a setting for marquee message.
     */
    public function updateMarqueeSettings(Request $request)
    {
        $request->validate([
            'commonMessage' => 'required|string',
            'items' => 'required|array',
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => 'marquee_settings'],
            ['value' => $request->all()]
        );

        return $this->successResponse($setting->value, 'Marquee settings updated successfully');
    }
}
