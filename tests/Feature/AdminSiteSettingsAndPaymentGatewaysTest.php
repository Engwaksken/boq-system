<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSiteSettingsAndPaymentGatewaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_site_settings(): void
    {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach($superAdminRole);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.settings'));

        $response->assertStatus(200);

        Livewire::actingAs($superAdmin)
            ->test(\App\Livewire\Admin\SiteSettings::class)
            ->set('settings.system_name', 'Updated BOQ System')
            ->set('settings.currency', 'USD')
            ->call('save');

        $this->assertEquals('Updated BOQ System', SiteSetting::get('system_name'));
        $this->assertEquals('USD', SiteSetting::get('currency'));
    }

    public function test_super_admin_can_manage_payment_gateways(): void
    {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach($superAdminRole);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.payment-gateways'));

        $response->assertStatus(200);

        Livewire::actingAs($superAdmin)
            ->test(\App\Livewire\Admin\PaymentGateways::class)
            ->set('form.name', 'New Gateway')
            ->set('form.code', 'new_gateway')
            ->set('form.driver', 'stripe')
            ->set('form.payment_timeout_seconds', 1200)
            ->call('save');

        $this->assertDatabaseHas('payment_gateways', [
            'code' => 'new_gateway',
            'name' => 'New Gateway',
        ]);
    }
}
