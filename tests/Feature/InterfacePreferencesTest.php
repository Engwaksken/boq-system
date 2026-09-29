<?php

namespace Tests\Feature;

use App\Livewire\Preferences;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Preferences page (theme, accent, density, sidebar, font size), the quick
 * light/dark switch and the <html> data attributes the layout outputs.
 */
class InterfacePreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function customer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));

        return $user;
    }

    public function test_preferences_page_renders_for_a_signed_in_user(): void
    {
        $this->actingAs($this->customer())
            ->get(route('preferences'))
            ->assertOk()
            ->assertSeeLivewire(Preferences::class)
            ->assertSee('Theme')
            ->assertSee('Accent colour')
            ->assertSee('Density')
            ->assertSee('Font size')
            ->assertSee('Save preferences');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('preferences'))->assertRedirect(route('login'));
        $this->post(route('preferences.theme'), ['theme' => 'dark'])->assertRedirect(route('login'));
    }

    public function test_sidebar_shows_preferences_for_every_user(): void
    {
        $this->actingAs($this->customer())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('preferences'), false)
            ->assertSee('Preferences');
    }

    public function test_defaults_are_used_when_nothing_is_saved(): void
    {
        $user = $this->customer();

        $this->assertSame([
            'theme' => 'light',
            'accent' => 'green',
            'density' => 'comfortable',
            'sidebar' => 'expanded',
            'font' => 'default',
        ], $user->interfacePreferences());
        $this->assertSame('light', $user->preference('theme'));
        $this->assertSame('fallback', $user->preference('unknown', 'fallback'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('data-theme="light"', false)
            ->assertSee('data-accent="green"', false)
            ->assertSee('data-density="comfortable"', false)
            ->assertSee('data-font="default"', false);
    }

    public function test_saving_valid_values_persists_them(): void
    {
        $user = $this->customer(['preferences' => ['keep' => 'me']]);

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('theme', 'dark')
            ->set('accent', 'purple')
            ->set('density', 'compact')
            ->set('sidebar', 'collapsed')
            ->set('font', 'large')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('preferences'));

        $user->refresh();

        $this->assertSame('dark', $user->preference('theme'));
        $this->assertSame('purple', $user->preference('accent'));
        $this->assertSame('compact', $user->preference('density'));
        $this->assertSame('collapsed', $user->preference('sidebar'));
        $this->assertSame('large', $user->preference('font'));
        $this->assertSame('me', $user->preferences['keep']);
        $this->assertSame('Your preferences have been saved.', session('success'));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->set('theme', 'neon')
            ->set('accent', 'chartreuse')
            ->set('density', 'tiny')
            ->set('sidebar', 'hidden')
            ->set('font', 'huge')
            ->call('save')
            ->assertHasErrors(['theme', 'accent', 'density', 'sidebar', 'font']);

        $this->assertNull($user->fresh()->preferences);
    }

    public function test_tampered_stored_values_fall_back_to_defaults(): void
    {
        $user = $this->customer(['preferences' => ['theme' => '"><script>', 'accent' => 'blue']]);

        $this->assertSame('light', $user->preference('theme'));
        $this->assertSame('blue', $user->preference('accent'));
    }

    public function test_layout_outputs_saved_preferences_as_data_attributes(): void
    {
        $user = $this->customer(['preferences' => [
            'theme' => 'system',
            'accent' => 'orange',
            'density' => 'compact',
            'sidebar' => 'collapsed',
            'font' => 'large',
        ]]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="system"', false)
            ->assertSee('data-accent="orange"', false)
            ->assertSee('data-density="compact"', false)
            ->assertSee('data-font="large"', false)
            ->assertSee('data-sidebar="collapsed"', false)
            ->assertSee('boq-shell-collapsed', false);
    }

    public function test_restore_defaults_resets_the_form(): void
    {
        $user = $this->customer(['preferences' => ['theme' => 'dark', 'accent' => 'rose']]);

        Livewire::actingAs($user)
            ->test(Preferences::class)
            ->assertSet('theme', 'dark')
            ->assertSet('accent', 'rose')
            ->call('restoreDefaults')
            ->assertSet('theme', 'light')
            ->assertSet('accent', 'green')
            ->assertDispatched('preferences-preview');
    }

    public function test_top_bar_toggle_saves_the_theme_mode(): void
    {
        $user = $this->customer(['preferences' => ['accent' => 'teal']]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee(route('preferences.theme'), false)
            ->assertSee('Dark mode');

        $this->actingAs($user)
            ->postJson(route('preferences.theme'), ['theme' => 'dark'])
            ->assertOk()
            ->assertJson(['theme' => 'dark']);

        $this->assertSame('dark', $user->fresh()->preference('theme'));
        $this->assertSame('teal', $user->fresh()->preference('accent'));

        $this->actingAs($user)
            ->postJson(route('preferences.theme'), ['theme' => 'purple'])
            ->assertStatus(422);

        $this->assertSame('dark', $user->fresh()->preference('theme'));
    }

    public function test_guest_pages_stay_in_the_light_theme(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-theme=', false);
    }
}
