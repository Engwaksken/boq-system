<?php

namespace Tests\Feature;

use App\Livewire\Projects\Edit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebProjectEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleared_fields_and_formatted_contract_value_are_saved(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'client' => 'Old client',
            'start_date' => '2026-01-01',
            'contract_value' => 100,
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class, ['project' => $project])
            ->set('name', 'Renamed')
            ->set('client', '')
            ->set('startDate', '')
            ->set('expectedCompletionDate', '')
            ->set('contractValue', '1,250,000')
            ->set('currency', 'ugx')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $project->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertNull($fresh->client);
        $this->assertNull($fresh->start_date);
        $this->assertEquals(1250000, $fresh->contract_value);
        $this->assertSame('UGX', $fresh->currency);
        $this->assertSame('active', $fresh->status);
    }
}
