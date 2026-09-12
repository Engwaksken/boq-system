<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_requires_the_matching_sanctum_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mcp', ['boq.read'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/mcp/tools/calculate_boq_total', ['parameters' => []])
            ->assertForbidden()
            ->assertJsonPath('code', 'MCP_PERMISSION_DENIED');
    }

    public function test_mcp_lists_only_projects_from_the_service_accounts_organisation(): void
    {
        $user = User::factory()->create();
        $own = Project::factory()->create(['organisation_id' => $user->organisation_id]);
        Project::factory()->create();
        $token = $user->createToken('mcp', ['boq.read'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/mcp/tools/list_boq_projects', ['parameters' => []])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $own->id)
            ->assertJsonCount(1, 'data.items');
    }
}
