<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_notifications()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\DummyNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test Notification'],
            'read_at' => null,
        ]);

        $response = $this->getJson('/api/v1/user/notifications');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         '*' => [
                             'id', 'type', 'data', 'read_at', 'created_at'
                         ]
                     ]
                 ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $notificationId = Str::uuid();

        DatabaseNotification::create([
            'id' => $notificationId,
            'type' => 'App\Notifications\DummyNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test Notification'],
            'read_at' => null,
        ]);

        $response = $this->postJson("/api/v1/user/notifications/{$notificationId}/read");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 200,
                     'message' => 'Notification marked as read'
                 ]);

        $this->assertNotNull(DatabaseNotification::find($notificationId)->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\DummyNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'N1'],
            'read_at' => null,
        ]);

        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\DummyNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'N2'],
            'read_at' => null,
        ]);

        $response = $this->postJson("/api/v1/user/notifications/read-all");

        $response->assertStatus(200);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }
}
