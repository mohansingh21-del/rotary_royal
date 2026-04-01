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
                'id' => $user->id,
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
        $request->user()->currentAccessToken()->delete();

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
}
