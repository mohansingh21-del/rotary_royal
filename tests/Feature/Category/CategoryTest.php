<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_categories()
    {
        Category::factory()->count(2)->create(['is_active' => 1]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_admin_can_retrieve_categories()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        Category::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_category()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Community Service',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'name' => 'Community Service',
        ]);
    }

    public function test_admin_can_view_category()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();

        $response = $this->getJson("/api/v1/admin/categories/{$category->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $category->id);
    }

    public function test_admin_can_toggle_category_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create(['is_active' => 1]);

        $response = $this->patchJson("/api/v1/admin/categories/{$category->id}/status");

        $response->assertStatus(200);
        $this->assertEquals(0, $category->fresh()->is_active);
    }

    public function test_admin_can_delete_category()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
