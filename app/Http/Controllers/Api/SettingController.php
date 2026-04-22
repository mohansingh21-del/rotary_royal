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
        $setting = Setting::first();

        if (!$setting || !isset($setting->$key)) {
            return $this->errorResponse('Setting not found', 404);
        }

        return $this->successResponse($setting->$key, "Setting for $key retrieved successfully");
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
            'qrCode' => 'nullable|string',
        ]);

        $setting = Setting::first();
        if (!$setting) {
            $setting = new Setting();
        }

        $setting->account_holder_name = $request->accountName;
        $setting->account_number = $request->accountNumber;
        $setting->ifsc_code = $request->ifscCode;
        $setting->bank_name = $request->bankName;
        // Note: qrCode is a string here for legacy compatibility
        $setting->payment_qr_code = $request->qrCode;
        $setting->save();

        return $this->successResponse($setting, 'Bank details updated successfully');
    }

    /**
     * Get donation settings.
     */
    public function getDonationSettings()
    {
        $setting = Setting::first();

        if (!$setting) {
            return response()->json([
                'status' => 200,
                'message' => 'Donation settings not found',
                'data' => [],
            ]);
        }

        $data = $setting->toArray();
        if ($setting->payment_qr_code) {
            $data['payment_qr_code_url'] = asset($setting->payment_qr_code);
        }

        return $this->successResponse($data, 'Donation settings retrieved successfully');
    }

    /**
     * Update or create donation settings.
     */
    public function updateDonationSettings(Request $request)
    {
        $request->validate([
            // Admin Bank Details
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'ifsc_code' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'branch' => 'required|string|max:255',
            // Club Details
            'contact_numbers' => 'required|string|max:255',
            'email_address' => 'required|email|max:255',
            'office_address' => 'required|string',
            // UPI & QR Code
            'upi_id' => 'required|string|max:255',
            'payment_qr_code' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
        ]);

        $setting = Setting::first();
        if (!$setting) {
            $setting = new Setting();
        }

        // Handle QR Code Upload
        if ($request->hasFile('payment_qr_code')) {
            // Delete old QR code if exists
            if ($setting->payment_qr_code && file_exists(public_path($setting->payment_qr_code))) {
                @unlink(public_path($setting->payment_qr_code));
            }
            $file = $request->file('payment_qr_code');
            $filename = 'qr_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('settings/qr_codes'), $filename);
            $setting->payment_qr_code = '/settings/qr_codes/' . $filename;
        }

        $setting->account_holder_name = $request->account_holder_name;
        $setting->account_number = $request->account_number;
        $setting->ifsc_code = $request->ifsc_code;
        $setting->bank_name = $request->bank_name;
        $setting->branch = $request->branch;
        $setting->contact_numbers = $request->contact_numbers;
        $setting->email_address = $request->email_address;
        $setting->office_address = $request->office_address;
        $setting->upi_id = $request->upi_id;
        
        $setting->save();

        return $this->successResponse($setting, 'Donation settings updated successfully');
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

        $setting = Setting::first();
        if (!$setting) {
            $setting = new Setting();
        }

        $setting->marquee_common_message = $request->commonMessage;
        $setting->marquee_items = $request->items;
        $setting->save();

        return $this->successResponse($setting, 'Marquee settings updated successfully');
    }
}
