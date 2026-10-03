<?php

namespace Tests\Feature;

use App\Jobs\NotifyHardwarePriceChange;
use App\Models\HardwareBookmark;
use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\HardwarePriceChanged;
use App\Services\HardwarePriceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HardwarePriceAlertTest extends TestCase
{
    use RefreshDatabase;

    private function price(User $user, array $overrides = []): HardwarePrice
    {
        return HardwarePrice::create(array_merge([
            'organisation_id' => $user->organisation_id,
            'item_name' => 'Portland cement 50kg',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => 'bag',
            'price' => 36000,
            'currency' => 'UGX',
            'supplier' => 'Hima',
            'location' => 'Kampala',
            'source_url' => 'https://example.com/cement',
            'fetched_at' => now(),
            'last_verified_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function history(HardwarePrice $price, float $value = 35000): PriceHistory
    {
        return PriceHistory::create([
            'organisation_id' => $price->organisation_id,
            'hardware_price_id' => $price->id,
            'price' => $value,
            'currency' => $price->currency,
            'supplier' => $price->supplier,
            'location' => $price->location,
            'source_url' => $price->source_url,
            'recorded_at' => now(),
            'metadata' => ['change' => 'price_update'],
        ]);
    }

    private function bookmark(User $user, HardwarePrice $price): void
    {
        HardwareBookmark::create([
            'user_id' => $user->id,
            'hardware_price_id' => $price->id,
            'location' => $price->location ?? 'Kampala',
        ]);
    }

    /** @param array<string, bool|string> $prefs */
    private function setPreferences(User $user, array $prefs): void
    {
        $user->forceFill(['notification_preferences' => $prefs])->save();
    }

    public function test_a_price_change_alerts_bookmarked_users_in_app_and_by_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $price = $this->price($user);
        $this->bookmark($user, $price);
        $this->setPreferences($user, ['email_price_alerts' => true, 'in_app_price_alerts' => true]);

        NotifyHardwarePriceChange::dispatchSync($this->history($price)->id);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'price_alert',
        ]);
        Notification::assertSentTo($user, HardwarePriceChanged::class);
    }

    public function test_no_alert_is_sent_when_both_channels_are_disabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $price = $this->price($user);
        $this->bookmark($user, $price);
        $this->setPreferences($user, ['email_price_alerts' => false, 'in_app_price_alerts' => false]);

        NotifyHardwarePriceChange::dispatchSync($this->history($price)->id);

        $this->assertDatabaseCount('user_notifications', 0);
        Notification::assertNothingSent();
    }

    public function test_a_history_row_only_alerts_once_even_when_the_job_is_retried(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $price = $this->price($user);
        $this->bookmark($user, $price);
        $this->setPreferences($user, ['email_price_alerts' => true, 'in_app_price_alerts' => true]);

        $history = $this->history($price);
        NotifyHardwarePriceChange::dispatchSync($history->id);
        NotifyHardwarePriceChange::dispatchSync($history->id);

        $this->assertDatabaseCount('user_notifications', 1);
        Notification::assertSentToTimes($user, HardwarePriceChanged::class, 1);
    }

    public function test_unverified_users_are_not_alerted(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $price = $this->price($user);
        $this->bookmark($user, $price);
        $this->setPreferences($user, ['email_price_alerts' => true, 'in_app_price_alerts' => true]);

        NotifyHardwarePriceChange::dispatchSync($this->history($price)->id);

        $this->assertDatabaseCount('user_notifications', 0);
        Notification::assertNothingSent();
    }

    public function test_an_email_only_alert_is_hidden_from_the_notification_centre(): void
    {
        $user = User::factory()->create();
        $price = $this->price($user);
        $this->bookmark($user, $price);
        $this->setPreferences($user, ['email_price_alerts' => true, 'in_app_price_alerts' => false]);

        NotifyHardwarePriceChange::dispatchSync($this->history($price)->id);

        // The row is kept for auditing/deduplication, but must not surface in-app.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'price_alert']);
        UserNotification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Welcome',
            'message' => 'Account ready.',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame(1, $response->json('data.total'));
    }

    public function test_a_manual_price_change_dispatches_the_alert_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $price = $this->price($user);

        app(HardwarePriceManager::class)->update($user->organisation_id, $price, [
            'item_name' => 'Portland cement 50kg',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => 'bag',
            'price' => 37000,
            'currency' => 'UGX',
            'supplier' => 'Hima',
            'location' => 'Kampala',
            'source_url' => 'https://example.com/cement',
            'fetched_at' => now()->toDateTimeString(),
            'is_active' => true,
        ]);

        Queue::assertPushed(NotifyHardwarePriceChange::class);
        $this->assertDatabaseHas('price_histories', ['hardware_price_id' => $price->id]);
    }

    public function test_an_unchanged_manual_price_does_not_dispatch_an_alert(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $price = $this->price($user);

        app(HardwarePriceManager::class)->update($user->organisation_id, $price, [
            'item_name' => 'Portland cement 50kg',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => 'bag',
            'price' => 36000,
            'currency' => 'UGX',
            'supplier' => 'Hima',
            'location' => 'Kampala',
            'source_url' => 'https://example.com/cement',
            'fetched_at' => now()->toDateTimeString(),
            'is_active' => true,
        ]);

        Queue::assertNotPushed(NotifyHardwarePriceChange::class);
    }
}
