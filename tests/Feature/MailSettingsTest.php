<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteSettings;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MailSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    public function test_admin_can_store_smtp_settings_that_override_the_mail_config(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(SiteSettings::class)
            ->set('settings.mail_mailer', 'smtp')
            ->set('settings.mail_host', 'smtp.example.com')
            ->set('settings.mail_port', 587)
            ->set('settings.mail_username', 'mailer')
            ->set('settings.mail_password', 'secret-pass')
            ->set('settings.mail_encryption', 'tls')
            ->set('settings.mail_from_address', 'no-reply@example.com')
            ->set('settings.mail_from_name', 'BOQ Desk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('smtp.example.com', SiteSetting::get('mail_host'));
        $this->assertNotSame('secret-pass', SiteSetting::get('mail_password'));
        $this->assertSame('secret-pass', app(MailSettings::class)->password());

        app(MailSettings::class)->apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));
    }

    public function test_send_test_email_sends_and_non_admin_is_forbidden(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        Livewire::actingAs($admin)->test(SiteSettings::class)
            ->set('settings.mail_mailer', 'log')
            ->call('sendTestMail')
            ->assertHasNoErrors();
        Mail::assertSent(\App\Mail\MailSettingsTest::class);

        Livewire::actingAs(User::factory()->create())->test(SiteSettings::class)->assertForbidden();
    }
}
