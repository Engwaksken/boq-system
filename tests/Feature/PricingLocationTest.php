<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Show;
use App\Models\Boq;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingLocationTest extends TestCase
{
    use RefreshDatabase;

    private function boqWithoutLocation(User $user): Boq
    {
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'location' => null,
            'district' => null,
            'country' => null,
        ]);

        return Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id]);
    }

    public function test_pricing_location_falls_back_to_the_user_then_the_market_location(): void
    {
        SiteSetting::set('market_location', '');
        SiteSetting::set('country', '');
        $user = User::factory()->create(['location' => null]);
        $boq = $this->boqWithoutLocation($user);

        $this->assertSame('', $boq->pricingLocation($user));

        SiteSetting::set('market_location', 'Owino Market, Kampala');
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame('Owino Market, Kampala', $boq->fresh()->pricingLocation($user));

        $user->forceFill(['location' => 'Gulu'])->save();
        $this->assertSame('Gulu', $boq->fresh()->pricingLocation($user->fresh()));

        $boq->project->update(['district' => 'Wakiso']);
        $this->assertSame('Wakiso', $boq->fresh()->pricingLocation($user));
    }

    public function test_boq_page_asks_for_the_location_and_saves_it_to_the_project(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::set('market_location', '');
        SiteSetting::set('country', '');
        \Illuminate\Support\Facades\Cache::flush();
        $user = User::factory()->create(['location' => null]);
        $user->roles()->attach(\App\Models\Role::where('slug', 'user')->value('id'));
        $boq = $this->boqWithoutLocation($user);

        $page = Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->assertSee('Where is this project?')
            ->call('generateBoq')
            ->assertHasErrors('projectLocation');

        $page->set('projectLocation', 'Mukono')->call('saveProjectLocation')->assertHasNoErrors();

        $this->assertSame('Mukono', $boq->project->fresh()->location);
        $this->assertSame('Mukono', $boq->fresh()->pricingLocation($user));
    }
}
