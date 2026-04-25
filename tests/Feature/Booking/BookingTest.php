<?php

namespace Tests\Feature\Booking;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_a_booking()
    {
        $asset = Asset::factory()->create([
            'quantity' => 10,
            'left_quantity' => 10,
            'price' => 1500,
        ]);

        $paymentFile = UploadedFile::fake()->image('payment.jpg');
        $idFile = UploadedFile::fake()->image('id.jpg');

        $response = $this->postJson('/api/v1/bookings', [
            'asset_id' => $asset->id,
            'user_name' => 'Guest User',
            'user_email' => 'guest@example.com',
            'user_phone' => '9876543210',
            'start_date' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'id_number' => 'ABC123456',
            'id_image_path' => $idFile,
            'payment_image' => $paymentFile,
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'status' => 201,
                     'message' => 'Your booking has been approved successfully.',
                 ]);

        $this->assertDatabaseHas('bookings', [
            'user_email' => 'guest@example.com',
            'asset_id' => $asset->id,
            'status' => 'Approved',
        ]);
        
        $this->assertDatabaseHas('users', [
            'email' => 'guest@example.com',
            'role' => 'Non-Member'
        ]);
    }

    public function test_booking_fails_if_asset_unavailable()
    {
        $asset = Asset::factory()->create([
            'quantity' => 1,
            'left_quantity' => 0, // Fully booked
            'price' => 0,
        ]);

        $idFile = UploadedFile::fake()->image('id.jpg');

        $response = $this->postJson('/api/v1/bookings', [
            'asset_id' => $asset->id,
            'user_name' => 'Guest User',
            'user_email' => 'guest2@example.com',
            'user_phone' => '9876543211',
            'start_date' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'id_number' => 'ABC123456',
            'id_image_path' => $idFile,
        ]);

        $response->assertStatus(409)
                 ->assertJson([
                     'status' => 409,
                     'message' => 'Not Available: No more units of this asset are available.',
                 ]);
    }

    public function test_admin_can_reject_a_booking_and_restore_quantity()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $asset = Asset::factory()->create(['left_quantity' => 5, 'quantity' => 10]);
        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'status' => 'Approved'
        ]);

        $response = $this->postJson('/api/v1/admin/bookings/reject', [
            'id' => $booking->id,
            'reason' => 'Invalid ID uploaded',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'Rejected',
            'rejection_reason' => 'Invalid ID uploaded',
        ]);

        // Left quantity should be incremented from 5 to 6
        $this->assertEquals(6, $asset->fresh()->left_quantity);
    }

    public function test_admin_can_update_booking_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $asset = Asset::factory()->create(['left_quantity' => 5, 'quantity' => 10]);
        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'status' => 'Rejected'
        ]);

        // Change from Rejected to Approved
        $response = $this->patchJson("/api/v1/admin/bookings/{$booking->id}/status", [
            'status' => 'Approved',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'Approved',
        ]);

        // Asset quantity should decrement since it is approved
        $this->assertEquals(4, $asset->fresh()->left_quantity);
    }

    public function test_user_can_view_own_bookings()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        Booking::factory()->count(3)->create(['user_id' => $user->id]);
        Booking::factory()->create(); // other user's booking

        $response = $this->getJson('/api/v1/user/user-bookings');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_view_others_booking_details()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $otherBooking = Booking::factory()->create(); // created for another random user

        $response = $this->getJson("/api/v1/user/user-bookings/{$otherBooking->id}");

        $response->assertStatus(404);
    }
}
