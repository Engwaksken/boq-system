<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\LanguagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $viewerRole = Role::factory()->create(['slug' => 'viewer']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organisation_name' => 'Acme Ltd',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('organisations', ['name' => 'Acme Ltd']);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertNotNull($user->organisation_id);
        $this->assertTrue($user->is_active);
    }

    public function test_register_without_organisation_name_creates_user_without_org(): void
    {
        $viewerRole = Role::factory()->create(['slug' => 'viewer']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Solo User',
            'email' => 'solo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'solo@example.com')->first();
        $this->assertNull($user->organisation_id);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_accepts_country_and_profile_languages(): void
    {
        (new LanguagesSeeder)->run();
        Role::factory()->create(['slug' => 'viewer']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Luganda User',
            'email' => 'luganda@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'country' => 'Uganda',
            'original_language' => 'lg',
            'report_language' => 'en',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.country', 'Uganda')
            ->assertJsonPath('data.user.original_language', 'lg')
            ->assertJsonPath('data.user.report_language', 'en');

        $this->assertDatabaseHas('users', [
            'email' => 'luganda@example.com',
            'country' => 'Uganda',
            'original_language' => 'lg',
            'report_language' => 'en',
        ]);
    }

    public function test_register_rejects_unknown_language_codes(): void
    {
        (new LanguagesSeeder)->run();
        Role::factory()->create(['slug' => 'viewer']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Bad Lang',
            'email' => 'badlang@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'original_language' => 'xx',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('original_language');
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_with_disabled_account_is_denied(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $user->forceFill(['is_active' => false])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'ACCOUNT_DISABLED']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'before@example.com',
            'password' => Hash::make('old-password'),
            'locale' => 'en',
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/auth/profile', [
            'name' => 'Updated User',
            'email' => 'after@example.com',
            'locale' => 'lg',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.email', 'after@example.com');

        $user->refresh();
        $this->assertSame('Updated User', $user->name);
        $this->assertSame('lg', $user->locale);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_profile_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/auth/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
            'locale' => 'en',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_forgot_password_returns_confirmation_without_leaking_accounts(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_forgot_password_is_disabled_when_setting_off(): void
    {
        SiteSetting::set('allow_forgot_password', false);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'someone@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'PASSWORD_RESET_DISABLED']);
    }

    public function test_mobile_config_returns_managed_defaults(): void
    {
        SiteSetting::set('system_name', 'Test Boq');
        SiteSetting::set('splash_enabled', true);
        SiteSetting::set('allow_registration', true);
        SiteSetting::set('privacy_policy', 'Privacy text');

        $response = $this->getJson('/api/v1/mobile-config');

        $response->assertOk()
            ->assertJsonPath('data.system_name', 'Test Boq')
            ->assertJsonPath('data.splash_enabled', true)
            ->assertJsonPath('data.allow_registration', true)
            ->assertJsonPath('data.privacy_policy', 'Privacy text');
    }

    public function test_mobile_config_includes_countries_and_languages(): void
    {
        (new LanguagesSeeder)->run();

        $response = $this->getJson('/api/v1/mobile-config');

        $response->assertOk()
            ->assertJsonPath('data.countries.0', 'Uganda')
            ->assertJsonStructure(['data' => ['countries', 'languages']])
            ->assertJsonPath('data.languages.0.code', 'en');
    }
}
