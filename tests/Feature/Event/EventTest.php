<?php

namespace Tests\Feature\Event;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_events()
    {
        Event::factory()->count(3)->create(['is_active' => 1]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_public_can_view_event_details()
    {
        $event = Event::factory()->create(['is_active' => 1]);

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $event->id);
    }

    public function test_admin_can_create_event()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $bannerImage = UploadedFile::fake()->image('banner.jpg', 800, 400);
        $images = [
            UploadedFile::fake()->image('img1.jpg', 400, 400),
            UploadedFile::fake()->image('img2.jpg', 400, 400),
        ];

        $response = $this->postJson('/api/v1/admin/events', [
            'name' => 'Rotary Charity Run',
            'date' => now()->addDays(10)->format('Y-m-d'),
            'time' => '10:00',
            'location' => 'City Park',
            'latitude' => '40.7128',
            'longitude' => '-74.0060',
            'description' => 'A charity run event.',
            'category_id' => \App\Models\Category::factory()->create()->id,
            'is_active' => 1,
            'new_category' => null,
            'banner_image' => $bannerImage,
            'gallery_images' => $images
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'status' => 201,
                     'message' => 'Event created successfully',
                 ]);

        $this->assertDatabaseHas('events', [
            'name' => 'Rotary Charity Run',
        ]);
        
        $this->assertDatabasePathCount('event_images', 2);
    }

    protected function assertDatabasePathCount($table, $count)
    {
        $this->assertEquals($count, \Illuminate\Support\Facades\DB::table($table)->count());
    }

    public function test_admin_can_toggle_event_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $event = Event::factory()->create(['is_active' => 1]);

        $response = $this->patchJson("/api/v1/admin/events/{$event->id}/status");

        $response->assertStatus(200);

        $this->assertEquals(0, $event->fresh()->is_active);
    }
}
