<?php

namespace Tests\Browser;

use App\Models\Role;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FaqsManagerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::factory()->create(['slug' => 'super-admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_super_admin_can_open_faqs_route_and_see_faq_items_and_controls(): void
    {
        $this->withoutVite();
        Faq::create(['question' => 'How do I get help?', 'answer' => 'Contact support.', 'sort_order' => 2, 'is_active' => true]);

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.faqs'));

        $this->actingAs($this->superAdmin())->get(route('admin.faqs'))->assertOk()
            ->assertSee('FAQ Management')->assertSee('How do I get help?')->assertSee('Contact support.')
            ->assertSee('Add FAQ')->assertSee('Edit FAQ: How do I get help?');
    }

    public function test_super_admin_can_create_and_edit_faq_through_in_process_routes(): void
    {
        $this->withoutVite();
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('create')->assertSee('Create FAQ')
            ->set('question', 'New question?')->set('answer', 'New answer.')
            ->call('save')->assertSee('FAQ created successfully.')->assertDontSee('Create FAQ');

        $created = Faq::where('question', 'New question?')->firstOrFail();
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('edit', $created->id)->assertSee('Edit FAQ')->assertSet('question', 'New question?')
            ->set('question', 'Changed?')->set('answer', 'Changed answer.')
            ->call('save')->assertSee('FAQ updated successfully.');

        $this->assertDatabaseHas('faqs', ['id' => $created->id, 'question' => 'Changed?', 'answer' => 'Changed answer.']);
    }

    public function test_create_form_adds_and_removes_entries_and_submits_each_question_with_its_own_answer(): void
    {
        $component = Livewire::actingAs($this->superAdmin())
            ->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('create')
            ->assertSee('Create FAQ entries')
            ->assertSee('Add another FAQ');

        $component->call('addFaqEntry')
            ->assertSee('FAQ 2')
            ->call('addFaqEntry')
            ->assertSee('FAQ 3')
            ->call('removeFaqEntry', 1)
            ->assertDontSee('FAQ 3');

        $component->set('newFaqs.0.question', 'First question?')
            ->set('newFaqs.0.answer', 'First answer.')
            ->set('newFaqs.1.question', 'Second question?')
            ->set('newFaqs.1.answer', 'Second answer.')
            ->call('save')
            ->assertSee('FAQs created successfully.')
            ->assertDontSee('Create FAQ entries');

        $this->assertDatabaseHas('faqs', ['question' => 'First question?', 'answer' => 'First answer.']);
        $this->assertDatabaseHas('faqs', ['question' => 'Second question?', 'answer' => 'Second answer.']);
        $this->assertDatabaseCount('faqs', 2);
    }

    public function test_edit_form_keeps_single_faq_fields_and_existing_values(): void
    {
        $faq = Faq::create([
            'question' => 'Retained question?',
            'answer' => 'Retained answer.',
            'sort_order' => 6,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('edit', $faq->id)
            ->assertSee('Edit FAQ')
            ->assertSee('Save FAQ')
            ->assertSet('question', 'Retained question?')
            ->assertSet('answer', 'Retained answer.')
            ->assertDontSee('Add another FAQ');
    }

    public function test_public_component_state_contains_only_supported_faq_fields(): void
    {
        $faq = Faq::create([
            'question' => 'Existing question?',
            'answer' => 'Existing answer.',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($this->superAdmin())
            ->test(\App\Livewire\Admin\FaqsManager::class);

        $component->assertSet('faqs', [[
            'id' => $faq->id,
            'question' => 'Existing question?',
            'answer' => 'Existing answer.',
            'sort_order' => 4,
            'is_active' => true,
        ]]);
    }

    public function test_question_over_255_characters_is_rejected(): void
    {
        Livewire::actingAs($this->superAdmin())->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('create')
            ->set('question', str_repeat('q', 256))
            ->set('answer', 'A valid answer.')
            ->call('save')
            ->assertHasErrors(['question' => 'max']);

        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_answer_over_10000_characters_is_accepted(): void
    {
        $answer = str_repeat('a', 10001);

        Livewire::actingAs($this->superAdmin())->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('create')
            ->set('question', 'Long answer question?')
            ->set('answer', $answer)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('FAQ created successfully.');

        $this->assertDatabaseHas('faqs', ['question' => 'Long answer question?', 'answer' => $answer]);
    }

    public function test_invalid_form_values_are_shown_without_creating_a_faq(): void
    {
        Livewire::actingAs($this->superAdmin())->test(\App\Livewire\Admin\FaqsManager::class)
            ->call('create')->set('question', '')->set('answer', '')
            ->call('save')->assertHasErrors(['question' => 'required', 'answer' => 'required']);

        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_non_admin_is_denied_from_faqs_page_and_component(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.faqs'))->assertForbidden();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        Livewire::actingAs($user)->test(\App\Livewire\Admin\FaqsManager::class);
    }

    public function test_guest_is_redirected_from_faqs_route_to_login(): void
    {
        $this->get(route('admin.faqs'))->assertRedirect(route('login'));
    }
}
