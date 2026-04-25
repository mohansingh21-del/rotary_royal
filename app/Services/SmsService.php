<?php

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * External Integration Service wrapper for Outbound SMS providers.
 * Decouples actual Telecom logic from Auth pipelines allowing seamless provider swapping.
 */
class SmsService
{
    /**
     * Dispatch an OTP via external SMS provider dynamically.
     * Ensure this utilizes deferred queued processing (Delay/Jobs) in production arrays.
     *
     * @param string $mobile_no
     * @param string $otp
     * @return string
     */
    public function sendOtp(string $mobile_no, string $otp)
    {
        $message = "OTP for user verification is $otp. Regards, Team Rotary";
        $message .= "";
        return $message;
    }
}