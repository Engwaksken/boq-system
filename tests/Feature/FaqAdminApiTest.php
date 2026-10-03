<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FaqAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'is_system' => true],
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_super_admin_can_list_faqs(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/api/v1/admin/faqs', [
                'question' => 'How does it work?',
                'answer' => 'It works well.',
                'sort_order' => 3,
                'is_active' => true,
            ])->assertCreated();

        $this->actingAs($this->superAdmin())
            ->getJson('/api/v1/admin/faqs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.question', 'How does it work?')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_super_admin_can_create_faq(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/api/v1/admin/faqs', [
                'question' => 'Where can I get help?',
                'answer' => 'Contact support.',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.question', 'Where can I get help?');
    }

    public function test_super_admin_can_update_faq(): void
    {
        $create = $this->actingAs($this->superAdmin())->postJson('/api/v1/admin/faqs', [
            'question' => 'Old question',
            'answer' => 'Old answer',
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->putJson("/api/v1/admin/faqs/{$id}", ['question' => 'Updated question'])
            ->assertOk()
            ->assertJsonPath('data.question', 'Updated question');
    }

    public function test_guest_cannot_access_faq_admin_api(): void
    {
        $this->getJson('/api/v1/admin/faqs')->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_faq_admin_api(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/faqs')
            ->assertForbidden();
    }

    public function test_faq_batch_component_accepts_twenty_entries_and_rejects_a_twenty_first(): void
    {
        $component = Livewire::actingAs($this->superAdmin())
            ->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('create');

        for ($index = 1; $index < 20; $index++) {
            $component->call('addFaqEntry');
        }

        $component->assertCount('newFaqs', 20)
            ->call('addFaqEntry')
            ->assertCount('newFaqs', 20)
            ->assertHasErrors('newFaqs');
    }
}
