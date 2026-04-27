<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Validation\ValidationException;
use App\Services\SmsService;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Handle authentication and token issuance.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        if ($user->role !== 'Super Admin') {
            return $this->errorResponse('Access denied. Members cannot log in.', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'user_id' => $user->id,
                'member_id' => $user->member?->member_id,
                'image_url' => $user->member?->image ? asset($user->member->image) : null,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 'Login successful');
    }

    /**
     * Handle logout and token deletion.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->currentAccessToken()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $user->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * Send a 4-digit OTP for password reset.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('No account found with this email', 404);
        }

        $key = 'send-otp:' . $request->email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->errorResponse('Too many attempts. Please try again in ' . $seconds . ' seconds.', 429);
        }

        RateLimiter::hit($key, 60);

        $otp = rand(1000, 9999);

        // Clear existing resets for this email
        DB::table('password_resets')->where('email', $request->email)->delete();

        // Store hashed OTP
        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => Hash::make($otp),
            'created_at' => now(),
        ]);

        $user->notify(new ResetPasswordNotification($otp));

        if (config('app.env') === 'local') {
            return $this->successResponse(['otp' => $otp], 'OTP sent to your email (Local testing)');
        }

        return $this->successResponse(null, 'OTP sent to your email');
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric|digits:4',
            'password' => 'required|min:8|confirmed',
        ]);

        $key = 'reset-password:' . $request->email;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->errorResponse('Too many attempts. Please try again in ' . $seconds . ' seconds.', 429);
        }

        RateLimiter::hit($key, 60);

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (!$reset || !Hash::check($request->otp, $reset->token)) {
            return $this->errorResponse('Invalid OTP', 400);
        }

        if (now()->diffInMinutes($reset->created_at) > 15) {
            DB::table('password_resets')->where('email', $request->email)->delete();
            return $this->errorResponse('OTP expired', 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset record
        DB::table('password_resets')->where('email', $request->email)->delete();

        return $this->successResponse(null, 'Password reset successfully');
    }

    public function sendOtp(Request $request, SmsService $smsService)
    {
        $request->validate(
            [
                'phone' => 'required|digits:10',
            ],
            [
                'phone.required' => 'Phone number is required',
                'phone.digits' => 'Phone number must be 10 digits',
            ]
        );

        $user = User::where('phone', $request->phone)
            ->where('status', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Active user not found'
            ], 404);
        }

        // Prevent spam (1 OTP per 60 sec)
        if (Cache::has('otp_lock_' . $user->phone)) {
            return response()->json([
                'status' => 429,
                'message' => 'Please wait before requesting another OTP.'
            ], 429);
        }

        $otp = (string) random_int(1000, 9999);

        OtpVerification::updateOrCreate(
            ['phone' => $user->phone],
            [
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
            ]
        );

        Cache::put('otp_lock_' . $user->phone, true, now()->addSeconds(30));

        try {
            $smsService->sendOtp($user->phone, $otp);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to send OTP'
            ], 500);
        }



        return response()->json([
            'status' => 200,
            'message' => 'OTP sent successfully',
            'otp' => app()->environment('local') ? $otp : null, // Return OTP only in local env for testing
        ]);
    }

    public function resendOtp(Request $request, SmsService $smsService)
    {
        $request->validate([
            'phone' => 'required|digits:10',
        ]);

        $user = User::where('phone', $request->phone)
            ->where('status', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Active user not found'
            ], 404);
        }

        // Prevent spam (1 OTP per 60 sec)
        if (Cache::has('otp_lock_' . $user->phone)) {
            return response()->json([
                'status' => 429,
                'message' => 'Please wait before requesting another OTP.'
            ], 429);
        }

        $otp = (string) random_int(1000, 9999);

        OtpVerification::updateOrCreate(
            ['phone' => $user->phone],
            [
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
            ]
        );

        Cache::put('otp_lock_' . $user->phone, true, now()->addSeconds(60));

        try {
            $smsService->sendOtp($user->phone, $otp);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to resend OTP'
            ], 500);
        }

        return response()->json([
            'status' => 200,
            'message' => 'OTP resent successfully',
            'otp' => app()->environment('local') ? $otp : null, // Return OTP only in local env for testing
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|digits:10',
            'otp' => 'required|digits:4',
        ]);

        $otpVerification = OtpVerification::where('phone', $request->phone)->first();

        if (!$otpVerification || !$otpVerification->expires_at || $otpVerification->expires_at->isPast()) {
            OtpVerification::where('phone', $request->phone)->delete();
            return response()->json([
                'status' => 400,
                'message' => 'OTP expired or not found'
            ], 400);
        }

        if ($otpVerification->attempts >= 5) {
            return response()->json([
                'status' => 429,
                'message' => 'Too many attempts. Try again later.'
            ], 429);
        }

        if (!Hash::check($request->otp, $otpVerification->otp)) {
            $otpVerification->increment('attempts');

            return response()->json([
                'status' => 400,
                'message' => 'Invalid OTP'
            ], 400);
        }

        $user = User::where('phone', $request->phone)
            ->where('status', 1)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'Active user not found'
            ], 404);
        }

        $otpVerification->delete();

        // Generate token (Laravel Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 200,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'user_id' => $user->id,
                'member_id' => $user->member?->member_id,
                'image_url' => $user->member?->image ? asset($user->member->image) : null,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'role' => $user->role,
            ]
        ]);
    }
}
