<?php

namespace Tests\Feature;

use App\Exceptions\AiCreditExhaustedException;
use App\Livewire\Admin\AiProviders;
use App\Livewire\Admin\CategoriesManager;
use App\Models\AiProvider;
use App\Models\AiProviderUsage;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\HardwareCategory;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AiProviderService;
use App\Services\AiUsageMonitor;
use App\Services\BoqProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiCreditMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]));

        return $user;
    }

    private function deepseek(array $attributes = []): AiProvider
    {
        return AiProvider::create($attributes + [
            'key' => 'deepseek',
            'name' => 'DeepSeek',
            'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1',
            'default_model' => 'deepseek-chat',
            'api_key' => 'secret',
            'is_enabled' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    private function insufficientBalance(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response(['error' => ['message' => 'Insufficient Balance', 'type' => 'unknown_error']], 402)]);
    }

    public function test_out_of_credit_marks_the_provider_notifies_admins_and_is_not_retried(): void
    {
        $admin = $this->superAdmin();
        $provider = $this->deepseek();
        $this->insufficientBalance();

        try {
            app(AiProviderService::class)->json('Return JSON', 'test');
            $this->fail('Expected AiCreditExhaustedException.');
        } catch (AiCreditExhaustedException) {
        }

        $this->assertNotNull($provider->fresh()->credit_exhausted_at);
        $notification = UserNotification::where('user_id', $admin->id)->where('type', 'ai_provider_credit')->first();
        $this->assertNotNull($notification);
        $this->assertSame('Provider has no tokens or credit left', $notification->title);
        $this->assertStringNotContainsString('DeepSeek', $notification->title.' '.$notification->message);

        // The next request does not call the empty provider again, and no duplicate alert is sent.
        Http::fake();
        $this->expectException(AiCreditExhaustedException::class);
        try {
            app(AiProviderService::class)->json('Return JSON', 'test');
        } finally {
            Http::assertNothingSent();
            $this->assertSame(1, UserNotification::where('type', 'ai_provider_credit')->count());
        }
    }

    public function test_monthly_token_limit_and_expiry_are_reported(): void
    {
        $provider = $this->deepseek(['monthly_token_limit' => 1000, 'credit_expires_at' => now()->addDays(3)]);
        AiProviderUsage::create([
            'ai_provider_id' => $provider->id, 'provider_key' => 'deepseek', 'operation' => 'test',
            'input_units' => 600, 'output_units' => 350, 'successful' => true,
        ]);

        $monitor = app(AiUsageMonitor::class);
        $status = $monitor->status($provider->fresh());

        $this->assertSame('warning', $status['state']);
        $this->assertSame(3, $status['days_left']);
        $this->assertFalse($monitor->isExhausted($provider->fresh()));

        AiProviderUsage::create([
            'ai_provider_id' => $provider->id, 'provider_key' => 'deepseek', 'operation' => 'test',
            'input_units' => 100, 'output_units' => 0, 'successful' => true,
        ]);
        $this->assertTrue($monitor->isExhausted($provider->fresh()));
    }

    public function test_generate_boq_stops_calling_ai_once_credit_is_gone_and_explains_why(): void
    {
        $this->superAdmin();
        $this->deepseek();
        $this->insufficientBalance();
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'location' => 'Kampala']);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id]);
        foreach (['Unmatched item one', 'Unmatched item two', 'Unmatched item three'] as $description) {
            BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => $description, 'original_rate' => null, 'approved_rate' => null, 'ai_suggested_rate' => null, 'match_type' => null]);
        }

        $batch = app(BoqProcessingService::class)->start($boq, $user->id, $user->organisation_id)->fresh();

        Http::assertSentCount(1);
        $this->assertStringContainsString('no tokens or credit', (string) $batch->error_message);
        $this->assertSame(3, (int) $batch->failed_items);
    }

    public function test_ai_settings_show_usage_and_save_credit_fields(): void
    {
        $admin = $this->superAdmin();
        $provider = $this->deepseek();
        AiProviderUsage::create([
            'ai_provider_id' => $provider->id, 'provider_key' => 'deepseek', 'operation' => 'test',
            'input_units' => 1200, 'output_units' => 300, 'successful' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AiProviders::class)
            ->assertSee('AI Usage (this month)')
            ->assertSee('1.5K')
            ->call('edit', $provider->id)
            ->set('form.credit_balance', '12.5')
            ->set('form.credit_currency', 'usd')
            ->set('form.credit_expires_at', now()->addMonth()->toDateString())
            ->set('form.monthly_token_limit', 500000)
            ->call('save')
            ->assertHasNoErrors();

        $provider->refresh();
        $this->assertEquals(12.5, (float) $provider->credit_balance);
        $this->assertSame('USD', $provider->credit_currency);
        $this->assertSame(500000, $provider->monthly_token_limit);
    }

    public function test_deepseek_balance_is_read_automatically(): void
    {
        $provider = $this->deepseek();
        Http::fake(['api.deepseek.com/user/balance' => Http::response([
            'is_available' => true,
            'balance_infos' => [['currency' => 'USD', 'total_balance' => '4.20']],
        ])]);

        $this->assertTrue(app(AiUsageMonitor::class)->refreshBalance($provider));
        $this->assertEquals(4.2, (float) $provider->fresh()->credit_balance);
    }

    public function test_admins_can_manage_categories_including_materials(): void
    {
        $admin = User::factory()->create();
        // The seeded organisation admin role.
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']));

        $this->actingAs($admin)->get('/admin/categories')->assertOk();

        Livewire::actingAs($admin)
            ->test(CategoriesManager::class)
            ->call('setType', 'material')
            ->set('name', 'Solar Equipment')
            ->set('items', "Solar panel 300W\nInverter 5kVA\nSolar panel 300W")
            ->call('save')
            ->assertHasNoErrors();

        $category = HardwareCategory::where('name', 'Solar Equipment')->firstOrFail();
        $this->assertSame(['Inverter 5kVA', 'Solar panel 300W'], collect($category->itemNames())->sort()->values()->all());

        $plain = User::factory()->create();
        $this->actingAs($plain)->get('/admin/categories')->assertForbidden();
    }
}
