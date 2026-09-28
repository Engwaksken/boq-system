<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Create as BoqsCreate;
use App\Livewire\Boqs\Index as BoqsIndex;
use App\Livewire\HardwarePrices\Compare as HardwarePricesCompare;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Shell\NotificationsMenu;
use App\Models\Boq;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the web redesign: app shell, flash messages that were
 * never shown, and small UX fixes made while restyling the pages.
 */
class WebRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function customer(): User
    {
        $user = User::factory()->create(['name' => 'Peter Okello']);
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));

        return $user;
    }

    private function projectFor(User $user, array $attributes = []): Project
    {
        return Project::factory()->create($attributes + [
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);
    }

    public function test_customer_sidebar_lists_get_prices_and_shows_initials(): void
    {
        $this->actingAs($this->customer())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Get Prices')
            ->assertSee(route('hardware-prices.index'), false)
            ->assertSee(route('topups.index'), false)
            ->assertSee('PO')
            ->assertDontSee(route('admin.index'), false);
    }

    public function test_notification_menu_counts_and_marks_only_own_notifications(): void
    {
        $user = $this->customer();
        $other = User::factory()->create();

        UserNotification::create(['user_id' => $user->id, 'type' => 'subscription', 'title' => 'Plan renews soon', 'message' => 'Renews in 5 days.']);
        $foreign = UserNotification::create(['user_id' => $other->id, 'type' => 'subscription', 'title' => 'Someone else', 'message' => 'Hidden']);

        Livewire::actingAs($user)
            ->test(NotificationsMenu::class)
            ->assertSee('Plan renews soon')
            ->assertDontSee('Someone else')
            ->assertViewHas('unread', 1)
            ->call('markAllAsRead')
            ->assertViewHas('unread', 0)
            ->call('markAsRead', $foreign->id);

        $this->assertNull($foreign->fresh()->read_at);
        $this->assertSame(0, UserNotification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_project_delete_message_is_displayed_on_the_index(): void
    {
        $user = $this->customer();
        $project = $this->projectFor($user);

        Livewire::actingAs($user)
            ->test(ProjectsIndex::class)
            ->call('delete', $project->id)
            ->assertSee('Project deleted.');
    }

    public function test_project_page_shows_the_flash_after_saving(): void
    {
        $user = $this->customer();
        $project = $this->projectFor($user, ['contractor' => null]);

        $this->actingAs($user)
            ->withSession(['status' => 'Project created successfully.'])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project created successfully.')
            ->assertSee('—');
    }

    public function test_new_boq_form_preselects_an_accessible_project_only(): void
    {
        $user = $this->customer();
        $project = $this->projectFor($user);
        $foreign = Project::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);

        Livewire::withQueryParams(['project' => $project->id])
            ->actingAs($user)
            ->test(BoqsCreate::class)
            ->assertSet('projectId', $project->id);

        Livewire::withQueryParams(['project' => $foreign->id])
            ->actingAs($user)
            ->test(BoqsCreate::class)
            ->assertSet('projectId', null);
    }

    public function test_boq_list_can_be_filtered_by_project(): void
    {
        $user = $this->customer();
        $first = $this->projectFor($user, ['name' => 'First site']);
        $second = $this->projectFor($user, ['name' => 'Second site']);

        Boq::factory()->create(['project_id' => $first->id, 'organisation_id' => $user->organisation_id, 'name' => 'Foundations BOQ']);
        Boq::factory()->create(['project_id' => $second->id, 'organisation_id' => $user->organisation_id, 'name' => 'Roofing BOQ']);

        Livewire::actingAs($user)
            ->test(BoqsIndex::class)
            ->assertSee('Foundations BOQ')
            ->assertSee('Roofing BOQ')
            ->set('projectId', $first->id)
            ->assertSee('Foundations BOQ')
            ->assertDontSee('Roofing BOQ')
            ->set('projectId', '')
            ->assertSee('Roofing BOQ');
    }

    public function test_compare_page_shows_its_validation_message(): void
    {
        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(HardwarePricesCompare::class)
            ->call('compare')
            ->assertSee('Please select 2 to 10 items to compare.');
    }

    public function test_plans_page_counts_available_monthly_and_annual_plans(): void
    {
        \App\Models\Plan::factory()->create(['type' => 'monthly', 'is_active' => true, 'is_archived' => false]);
        \App\Models\Plan::factory()->create(['type' => 'annual', 'is_active' => true, 'is_archived' => false]);
        \App\Models\Plan::factory()->create(['type' => 'monthly', 'is_active' => false, 'is_archived' => false]);

        Livewire::actingAs($this->customer())
            ->test(\App\Livewire\Plans\Index::class)
            ->assertViewHas('stats', fn (array $stats) => $stats['available_plans'] === 2
                && $stats['monthly_plans'] === 1
                && $stats['annual_plans'] === 1);
    }

    public function test_choose_plan_link_opens_the_plans_tab(): void
    {
        Livewire::withQueryParams(['tab' => 'plans'])
            ->actingAs($this->customer())
            ->test(\App\Livewire\Subscriptions\Index::class)
            ->assertSet('activeTab', 'plans');
    }

    public function test_profile_overview_saves_for_a_user_on_an_active_non_default_language(): void
    {
        \App\Models\Language::updateOrCreate(['code' => 'lg'], ['name' => 'Luganda', 'native_name' => 'Luganda', 'is_active' => true]);
        $user = $this->customer();
        $user->forceFill(['locale' => 'lg'])->save();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Profile\Index::class)
            ->set('form.name', 'Peter O. Okello')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame('Peter O. Okello', $user->fresh()->name);
        $this->assertSame('lg', $user->fresh()->locale);
    }

    public function test_built_stylesheet_includes_pagination_utilities(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

        // Livewire's pagination view lives in vendor/, which Tailwind v4 does not scan by default.
        $this->assertStringContainsString('.rounded-l-md', $css);
        $this->assertStringContainsString('.boq-sidebar', $css);
    }
}
