<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_projects()
    {
        Project::factory()->count(2)->create(['is_active' => 1]);

        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_retrieve_projects()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        Project::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/admin/projects');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_project()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $image = UploadedFile::fake()->image('project.jpg', 800, 400);

        $response = $this->postJson('/api/v1/admin/projects', [
            'name' => 'Water Sanitation',
            'description' => 'Providing clean water.',
            'category_id' => \App\Models\Category::factory()->create()->id,
            'banner_image' => $image,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(30)->format('Y-m-d'),
            'goal_amount' => 10000,
            'raised_amount' => 500
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('projects', [
            'name' => 'Water Sanitation'
        ]);
    }

    public function test_admin_can_toggle_project_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $project = Project::factory()->create(['is_active' => 1]);

        $response = $this->patchJson("/api/v1/admin/projects/{$project->id}/status");

        $response->assertStatus(200);
        $this->assertEquals(0, $project->fresh()->is_active);
    }

    public function test_admin_can_delete_project()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $project = Project::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/projects/{$project->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
