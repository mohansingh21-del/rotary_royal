<?php

namespace Tests\Feature\Donation;

use App\Models\Donation;
use App\Models\Project;
use App\Models\User;
use App\Models\MarqueeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class DonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_make_a_donation()
    {
        $project = Project::factory()->create();

        $receiptFile = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->postJson('/api/v1/donations', [
            'project_id' => $project->id,
            'donor_name' => 'John Donor',
            'mobile_no' => '9888777666',
            'amount' => 5000,
            'payment_receipt' => $receiptFile,
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'status' => 201,
                     'message' => 'Donation recorded successfully',
                 ]);

        $this->assertDatabaseHas('donations', [
            'donor_name' => 'John Donor',
            'amount' => 5000,
        ]);

        $this->assertDatabaseHas('users', [
            'phone' => '9888777666',
        ]);
    }

    public function test_admin_can_retrieve_donations()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        Donation::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/donations');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_toggle_marquee_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $donation = Donation::factory()->create(['is_marquee' => true]);

        $response = $this->patchJson("/api/v1/admin/donations/{$donation->id}/marquee");

        $response->assertStatus(200);

        $this->assertEquals(0, $donation->fresh()->is_marquee);
    }

    public function test_user_can_view_their_own_donations()
    {
        $user = User::factory()->create(['phone' => '9111222333']);
        Sanctum::actingAs($user, ['*']);

        Donation::factory()->count(2)->create(['mobile_no' => '9111222333']);
        
        $response = $this->getJson('/api/v1/user/my-donations');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    public function test_public_can_view_marquee_message()
    {
        MarqueeMessage::create(['marquee_message' => 'Thank you donors']);
        Donation::factory()->create(['donor_name' => 'Alice', 'is_marquee' => true]);

        $response = $this->getJson('/api/v1/marquee-message');

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'marquee_message' => 'Thank you donors',
                 ]);
    }
}
