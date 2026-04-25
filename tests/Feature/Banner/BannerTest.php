<?php

namespace Tests\Feature\Banner;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_banners()
    {
        Banner::create([
            'image' => 'banner1.jpg',
            'status' => 1
        ]);

        $response = $this->getJson('/api/v1/banners');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_banner()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $image = UploadedFile::fake()->image('banner.jpg');

        $response = $this->postJson('/api/v1/admin/banners', [
            'image' => $image,
            'status' => 1
        ]);

        $response->assertStatus(201);
        $this->assertDatabasePathCount('banners', 1);
    }

    protected function assertDatabasePathCount($table, $count)
    {
        $this->assertEquals($count, \Illuminate\Support\Facades\DB::table($table)->count());
    }

    public function test_admin_can_toggle_banner_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $banner = Banner::create(['image' => 'b1.jpg', 'status' => 1]);

        $response = $this->patchJson("/api/v1/admin/banners/{$banner->id}/status");

        $response->assertStatus(200);
        $this->assertEquals(0, $banner->fresh()->status);
    }

    public function test_admin_can_delete_banner()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $banner = Banner::create(['image' => 'b1.jpg', 'status' => 1]);

        $response = $this->deleteJson("/api/v1/admin/banners/{$banner->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    }
}
