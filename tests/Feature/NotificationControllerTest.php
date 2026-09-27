<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        UserNotification::create(['user_id' => $user->id, 'title' => 'Mine', 'message' => 'Hello']);
        UserNotification::create(['user_id' => $other->id, 'title' => 'Theirs', 'message' => 'Hidden']);

        $this->actingAs($user)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Mine');
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::create(['user_id' => $user->id, 'title' => 'Mine', 'message' => 'Hello']);

        $this->actingAs($user)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Theirs',
            'message' => 'Hidden',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_profile_update_saves_phone_and_location(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/auth/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'en',
                'phone' => '+256700000000',
                'location' => 'Kampala',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+256700000000')
            ->assertJsonPath('data.user.location', 'Kampala');
    }
}
