<?php

namespace Tests\Feature\Asset;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_assets()
    {
        Asset::factory()->count(3)->create(['status' => 1]);

        $response = $this->getJson('/api/v1/assets');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_retrieve_assets()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        Asset::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/assets');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_asset()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $image = UploadedFile::fake()->image('asset.jpg', 64, 64);

        $response = $this->postJson('/api/v1/admin/assets', [
            'name' => 'Projector',
            'category' => 'Asset',
            'quantity' => 5,
            'price' => 500.00,
            'buffer_time' => 24,
            'image' => $image,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('assets', [
            'name' => 'Projector',
            'category' => 'Asset',
            'quantity' => 5,
        ]);
    }

    public function test_admin_can_toggle_asset_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $asset = Asset::factory()->create(['status' => 1]);

        $response = $this->patchJson("/api/v1/admin/assets/{$asset->id}/status");

        $response->assertStatus(200);
        $this->assertEquals(0, $asset->fresh()->status);
    }

    public function test_admin_can_delete_asset()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $asset = Asset::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/assets/{$asset->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }
}
