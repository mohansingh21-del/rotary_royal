<?php

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SmsService
{
    /**
     * Simulate sending an OTP via SMS.
     * Replace this with your actual outbound SMS gateway (Twilio, MSG91, etc).
     *
     * @param string $mobile_no
     * @param string $otp
     */
    public function sendOtp(string $mobile_no, string $otp)
    {
        $message = "OTP for user verification is $otp. Regards, Team Rotary";
        $message .= "";
        return $message;
    }
}