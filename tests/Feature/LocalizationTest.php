<?php

namespace Tests\Feature;

use App\Livewire\Admin\LanguagesManager;
use App\Models\Language;
use App\Models\Role;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_switch_the_sign_in_page_language(): void
    {
        $this->get('/login?lang=lg')
            ->assertOk()
            ->assertSee('Yingira')              // "Sign in"
            ->assertSee('lang="lg"', false);

        // The choice is remembered for the session.
        $this->get('/login')->assertSee('Yingira');
    }

    public function test_browser_language_is_used_for_guests(): void
    {
        $this->withHeader('Accept-Language', 'lg,en;q=0.8')
            ->get('/login')
            ->assertSee('Yingira');
    }

    public function test_signed_in_users_see_the_app_in_their_language(): void
    {
        $user = User::factory()->create(['locale' => 'lg']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ekisengejjero')        // "Dashboard"
            ->assertSee('Fuluma');              // "Log out"
    }

    public function test_inactive_languages_are_ignored(): void
    {
        Language::where('code', 'lg')->update(['is_active' => false]);

        $this->get('/login?lang=lg')->assertOk()->assertDontSee('Yingira');
    }

    public function test_admin_translation_overrides_apply_and_can_be_cleared(): void
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = User::factory()->create(['locale' => 'lg']);
        $admin->roles()->attach($role);
        $luganda = Language::where('code', 'lg')->firstOrFail();

        $component = Livewire::actingAs($admin)
            ->test(LanguagesManager::class)
            ->call('openTranslations', $luganda->id)
            ->set('translationSearch', 'Log out');

        $component->set('drafts.'.sha1('Log out'), 'Vaamu')->call('saveTranslations');

        $this->assertSame('Vaamu', Translation::linesFor('lg')['Log out']);
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Vaamu');

        // Blank reverts to the shipped translation.
        $component->set('drafts.'.sha1('Log out'), '')->call('saveTranslations');
        $this->assertArrayNotHasKey('Log out', Translation::linesFor('lg'));
    }

    public function test_every_wrapped_string_has_an_english_entry(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true);

        preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'\\)/", implode("\n", array_map(
            'file_get_contents',
            glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE)
        )), $matches);

        $missing = collect($matches[1])
            ->map(fn ($key) => stripslashes($key))
            ->filter(fn ($key) => ! str_contains($key, '.') || str_contains($key, ' ') || str_ends_with($key, '...'))
            ->reject(fn ($key) => array_key_exists($key, $english))
            ->unique()
            ->values();

        $this->assertSame([], $missing->all(), 'Missing from lang/en.json: '.$missing->implode(', '));
    }
}
