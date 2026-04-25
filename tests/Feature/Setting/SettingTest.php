<?php

namespace Tests\Feature\Setting;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_fetch_donation_settings()
    {
        // Depending on whether it uses Settings or a specialized model, let's just test the route response
        $response = $this->getJson('/api/v1/settings/donation');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data'
                 ]);
    }

    public function test_admin_can_update_donation_settings()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        // Fixed schema for donation settings updater expects specific keys.
        $response = $this->postJson('/api/v1/admin/settings/donation', [
            'account_holder_name' => 'Rotary Core',
            'account_number' => '1234567890',
            'ifsc_code' => 'IFSC001',
            'bank_name' => 'Global Bank',
            'branch' => 'Main',
            'contact_numbers' => '9998887776',
            'email_address' => 'contact@rotary.com',
            'office_address' => '123 Avenue',
            'upi_id' => 'rotary@upi'
        ]);

        $response->assertSuccessful();
    }

    public function test_admin_can_update_bank_details()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/v1/admin/settings/bank', [
            'bankName' => 'Rotary Bank',
            'accountNumber' => '123456789',
            'ifscCode' => 'IFSC0001',
            'accountName' => 'Rotary Club'
        ]);

        $response->assertSuccessful();
    }
}
