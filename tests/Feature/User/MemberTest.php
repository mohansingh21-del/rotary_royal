<?php

namespace Tests\Feature\User;

use App\Models\Members;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_retrieve_members()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        // Create generic members by hooking them to generic users
        Members::factory()->count(3)->create([
            'user_id' => User::factory()->create(['role' => 'Member'])->id
        ]);

        $response = $this->getJson('/api/v1/admin/members');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_member_with_user_account()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $image = UploadedFile::fake()->image('profile.jpg');

        $response = $this->postJson('/api/v1/admin/members', [
            'name' => 'Jane Rotary',
            'email' => 'jane@example.com',
            'phone' => '9998887776',
            'designation_id' => \App\Models\Category::factory()->create()->id,
            'blood_group' => 'O+',
            'work' => 'Tech Corp',
            'member_id' => 'ROT-001',
            'dob' => '1990-01-01',
            'gender' => 'Male',
            'address' => '123 Rotary Ave',
            'date_of_joining' => '2023-01-01',
            'image' => $image,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['phone' => '9998887776', 'role' => 'Member']);
        $this->assertDatabaseHas('members', ['work' => 'Tech Corp']);
    }

    public function test_admin_can_toggle_member_status()
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);
        Sanctum::actingAs($admin, ['*']);

        $user = User::factory()->create(['role' => 'Member', 'status' => 1]);
        $member = Members::factory()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/v1/admin/members/{$user->id}/status");

        $response->assertStatus(200);
        $this->assertEquals(0, $user->fresh()->status);
    }
}
