<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Models\Members;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_profile()
    {
        $user = User::factory()->create();
        $member = Members::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/user/profile');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'user_id',
                         'name',
                         'email',
                         'phone',
                         'member_id',
                         'address',
                         'dob',
                         'gender',
                         'image',
                         'work',
                         'profile_completed'
                     ]
                 ]);
                 
        $this->assertEquals($member->member_id, $response->json('data.member_id'));
    }

    public function test_authenticated_user_can_update_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/user/profile/update', [
            'name' => 'John Doe Updated',
            'email' => 'john.updated@example.com',
            'phone' => '9123456780',
            'member_id' => 'MEM-9999',
            'address' => '123 New Street',
            'dob' => '1990-01-01',
            'gender' => 'Male',
            'work' => 'Engineer',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 200,
                     'message' => 'Profile updated successfully',
                     'data' => [
                         'name' => 'John Doe Updated',
                         'email' => 'john.updated@example.com',
                         'phone' => '9123456780',
                         'member_id' => 'MEM-9999',
                         'address' => '123 New Street',
                         'dob' => '1990-01-01',
                         'gender' => 'Male',
                         'work' => 'Engineer',
                         'profile_completed' => true
                     ]
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'john.updated@example.com']);
        $this->assertDatabaseHas('members', ['member_id' => 'MEM-9999']);
    }

    public function test_authenticated_user_can_upload_profile_image()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/v1/user/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'member_id' => 'MEM-9998',
            'image' => $file,
        ]);

        $response->assertStatus(200);

        $member = Members::where('user_id', $user->id)->first();
        $this->assertNotNull($member->image);
        $this->assertStringContainsString('/members/', $member->image);
    }

    public function test_authenticated_user_can_deactivate_profile()
    {
        $user = User::factory()->create(['status' => 1]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/v1/user/profile/deactivate');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 200,
                     'message' => 'Profile deactivated successfully'
                 ]);

        $this->assertEquals(0, $user->fresh()->status);
    }
}
