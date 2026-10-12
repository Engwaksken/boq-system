<?php

namespace Tests\Feature;

use App\Models\NotificationDigestItem;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\ActivityDigest;
use App\Notifications\ActivityNotice;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function notify(User $user, ?string $dedupeKey = null): bool
    {
        return app(NotificationDispatcher::class)->notify(
            user: $user,
            category: 'expenses',
            type: 'expense',
            title: 'Expense recorded',
            message: 'Someone spent money.',
            url: 'https://example.test/expenses',
            dedupeKey: $dedupeKey,
        );
    }

    public function test_immediate_users_get_an_in_app_notice_and_an_email(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->notify($user));

        $this->assertSame(1, UserNotification::where('user_id', $user->id)->count());
        Notification::assertSentTo($user, ActivityNotice::class);
        $this->assertSame(0, NotificationDigestItem::count());
    }

    public function test_channels_follow_the_users_preferences(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_expenses' => false, 'in_app_expenses' => true],
        ]);

        $this->notify($user);

        $this->assertSame(1, UserNotification::where('user_id', $user->id)->count());
        Notification::assertNothingSent();

        $muted = User::factory()->create([
            'notification_preferences' => ['email_expenses' => false, 'in_app_expenses' => false],
        ]);

        $this->assertFalse($this->notify($muted));
        $this->assertSame(0, UserNotification::where('user_id', $muted->id)->count());
    }

    public function test_unverified_addresses_are_not_emailed(): void
    {
        $user = User::factory()->unverified()->create();

        $this->notify($user);

        Notification::assertNothingSent();
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->count());
    }

    public function test_dedupe_key_delivers_only_once(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->notify($user, 'once'));
        $this->assertFalse($this->notify($user, 'once'));

        $this->assertSame(1, UserNotification::where('user_id', $user->id)->count());
        Notification::assertSentToTimes($user, ActivityNotice::class, 1);
    }

    public function test_digest_users_have_emails_held_and_sent_by_the_matching_command(): void
    {
        $daily = User::factory()->create(['notification_preferences' => ['frequency' => 'daily']]);
        $weekly = User::factory()->create(['notification_preferences' => ['frequency' => 'weekly']]);

        $this->notify($daily);
        $this->notify($daily);
        $this->notify($weekly);

        Notification::assertNotSentTo($daily, ActivityNotice::class);
        $this->assertSame(3, NotificationDigestItem::whereNull('sent_at')->count());

        $this->artisan('notifications:digest daily')->assertSuccessful();

        Notification::assertSentTo($daily, ActivityDigest::class, fn (ActivityDigest $digest) => count($digest->items) === 2);
        Notification::assertNotSentTo($weekly, ActivityDigest::class);
        $this->assertSame(1, NotificationDigestItem::whereNull('sent_at')->count());

        // Already-sent items are not repeated.
        $this->artisan('notifications:digest daily')->assertSuccessful();
        Notification::assertSentToTimes($daily, ActivityDigest::class, 1);
    }

    public function test_digest_command_rejects_unknown_frequency(): void
    {
        $this->artisan('notifications:digest monthly')->assertFailed();
    }

    public function test_deadline_reminders_reach_owner_and_members_once(): void
    {
        $project = Project::factory()->create([
            'status' => 'active',
            'expected_completion_date' => now()->addDays(7)->toDateString(),
        ]);
        $member = User::factory()->create();
        ProjectAssignment::create(['project_id' => $project->id, 'user_id' => $member->id, 'role' => 'member']);

        $this->artisan('projects:remind')->assertSuccessful();
        $this->artisan('projects:remind')->assertSuccessful();

        foreach ([$project->user_id, $member->id] as $userId) {
            $this->assertSame(1, UserNotification::where('user_id', $userId)->where('type', 'project')->count());
        }
        Notification::assertSentToTimes($member, ActivityNotice::class, 1);
    }

    public function test_overdue_projects_are_reminded(): void
    {
        $project = Project::factory()->create([
            'status' => 'active',
            'expected_completion_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->artisan('projects:remind')->assertSuccessful();

        $this->assertSame(
            'Project deadline passed',
            UserNotification::where('user_id', $project->user_id)->value('title')
        );
    }
}
