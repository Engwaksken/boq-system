<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteSettings;
use App\Livewire\Profile\Index as ProfileIndex;
use App\Models\Country;
use App\Models\HardwareCategory;
use App\Models\HardwareItem;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\HardwarePriceFetchingService;
use App\Support\Regional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function price(User $user, array $attributes = []): HardwarePrice
    {
        return HardwarePrice::create($attributes + [
            'organisation_id' => $user->organisation_id,
            'item_name' => 'Portland Cement 42.5N',
            'brand' => 'Any',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'specification' => '50kg',
            'unit' => 'bag',
            'price' => 10,
            'currency' => 'USD',
            'supplier' => 'Builders Depot',
            'location' => 'Nairobi',
            'source_reference' => 'test',
            'fetched_at' => now(),
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------- reference data

    public function test_countries_table_is_seeded_worldwide(): void
    {
        $this->assertGreaterThan(190, Country::count());
        $this->assertSame('+81', Country::where('iso2', 'JP')->value('dial_code'));
        $this->assertSame('BRL', Country::where('iso2', 'BR')->value('currency_code'));
    }

    public function test_hardware_items_are_stored_in_their_own_table(): void
    {
        $cement = HardwareCategory::where('name', 'Cement')->firstOrFail();

        $this->assertTrue(HardwareItem::where('hardware_category_id', $cement->id)->where('name', 'White Cement')->exists());
        $this->assertContains('White Cement', $cement->itemNames());
    }

    public function test_editing_a_category_item_list_syncs_the_items_table(): void
    {
        $category = HardwareCategory::where('name', 'Cement')->firstOrFail();

        $category->update(['default_items' => ['Only This Cement']]);

        $this->assertSame(['Only This Cement'], $category->fresh()->itemNames());
        $this->assertFalse(HardwareItem::where('hardware_category_id', $category->id)->where('name', 'White Cement')->value('is_active'));
    }

    // ---------------------------------------------------------------- regional defaults

    public function test_regional_defaults_come_from_settings_not_uganda(): void
    {
        SiteSetting::where('key', 'currency')->delete();
        Regional::flush();
        config(['app.default_currency' => 'USD']);

        $this->assertSame('USD', Regional::currency());

        SiteSetting::set('currency', 'KES');
        SiteSetting::set('country', 'KE');
        SiteSetting::set('market_location', 'Nairobi');

        $this->assertSame('KES', Regional::currency());
        $this->assertSame('Kenya', Regional::countryName());
        $this->assertSame('Nairobi', Regional::marketLocation());
    }

    public function test_price_suppliers_and_locations_come_from_the_database(): void
    {
        $user = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $this->price($user);
        SiteSetting::set('market_location', 'Mombasa');

        $service = app(HardwarePriceFetchingService::class);

        $this->assertContains('Builders Depot', $service->getSuppliers());
        $this->assertSame(['Mombasa', 'Nairobi'], $service->getLocations());
    }

    public function test_admin_can_set_default_country_market_and_timezone(): void
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        Livewire::actingAs($admin)
            ->test(SiteSettings::class)
            ->set('settings.country', 'NG')
            ->set('settings.market_location', 'Lagos')
            ->set('settings.timezone', 'Africa/Lagos')
            ->set('settings.currency', 'USD')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('NG', SiteSetting::get('country'));
        $this->assertSame('Africa/Lagos', Regional::timezone());
    }

    // ---------------------------------------------------------------- profile

    public function test_notification_preferences_save_when_toggled(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileIndex::class)
            ->set('notificationPrefs.email_price_alerts', true)
            ->set('notificationPrefs.frequency', 'weekly')
            ->assertDispatched('notification-preferences-saved');

        $prefs = $user->fresh()->notificationPreferences();
        $this->assertTrue($prefs['email_price_alerts']);
        $this->assertSame('weekly', $prefs['frequency']);
    }

    public function test_display_preferences_save_and_update_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        Livewire::actingAs($user)
            ->test(ProfileIndex::class)
            ->set('displayPrefs.locale', 'lg')
            ->set('displayPrefs.date_format', 'd/m/Y')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('lg', $user->locale);
        $this->assertSame('d/m/Y', $user->displayPreferences()['date_format']);
    }

    public function test_bookmark_location_is_taken_from_the_selected_price(): void
    {
        $user = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $nairobi = $this->price($user);
        $this->price($user, ['location' => 'Kisumu', 'price' => 11]);

        Livewire::actingAs($user)
            ->test(ProfileIndex::class)
            ->call('setActiveTab', 'hardware-bookmarks')
            ->call('bookmarkHardware')
            ->set('hardwareBookmarkForm.hardware_price_id', $nairobi->id)
            ->assertSet('hardwareBookmarkForm.location', 'Nairobi')
            ->assertSee('Kisumu')
            ->set('bookmarkLocationChoice', 'Kisumu')
            ->assertSet('hardwareBookmarkForm.location', 'Kisumu')
            ->call('saveBookmark')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hardware_bookmarks', ['user_id' => $user->id, 'location' => 'Kisumu']);
    }

    public function test_every_text_field_on_key_forms_has_a_placeholder(): void
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertNoFieldsWithoutPlaceholder($html, $url);
        }

        $html = $this->actingAs($admin)->get(route('admin.settings'))->getContent();
        $this->assertNoFieldsWithoutPlaceholder($html, 'admin settings');
    }

    private function assertNoFieldsWithoutPlaceholder(string $html, string $page): void
    {
        preg_match_all('/<(input|textarea)\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match('/type="(hidden|checkbox|radio|file|submit|button)"/i', $tag)) {
                continue;
            }

            $this->assertMatchesRegularExpression('/placeholder=/i', $tag, "Field without placeholder on {$page}: {$tag}");
        }
    }
}
