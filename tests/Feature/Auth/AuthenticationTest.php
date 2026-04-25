<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use App\Models\OtpVerification;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_with_valid_credentials()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Super Admin',
            'status' => 1,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'token',
                         'token_type',
                         'user' => [
                             'user_id',
                             'email',
                             'role',
                         ],
                     ]
                 ]);
    }

    public function test_member_cannot_login_with_password()
    {
        $member = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Member',
            'status' => 1,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 403,
                     'message' => 'Access denied. Members cannot log in.',
                 ]);
    }

    public function test_login_fails_with_invalid_credentials()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Super Admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'status' => 401,
                     'message' => 'Invalid credentials',
                 ]);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 200,
                     'message' => 'Logged out successfully',
                 ]);
    }

    public function test_forgot_password_sends_otp()
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('password_resets', [
            'email' => 'reset@example.com',
        ]);
    }

    public function test_reset_password_with_valid_otp()
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $otp = '1234';

        DB::table('password_resets')->insert([
            'email' => 'reset@example.com',
            'token' => Hash::make($otp),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'otp' => '1234',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 200,
                     'message' => 'Password reset successfully',
                 ]);
                 
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_send_otp_via_phone_for_member_login()
    {
        $user = User::factory()->create([
            'phone' => '9123456780',
            'status' => 1,
            'role' => 'Member'
        ]);

        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '9123456780',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '9123456780',
        ]);
    }

    public function test_verify_otp_for_member_login()
    {
        $user = User::factory()->create([
            'phone' => '9123456789',
            'status' => 1,
            'role' => 'Member'
        ]);

        $otp = '1234';
        
        OtpVerification::create([
            'phone' => '9123456789',
            'otp' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '9123456789',
            'otp' => '1234',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'token',
                         'user' => [
                             'user_id',
                             'phone',
                         ]
                     ]
                 ]);
    }
}
