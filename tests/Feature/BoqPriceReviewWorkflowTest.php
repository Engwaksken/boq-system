<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Show;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoqPriceReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_and_approval_actions_require_their_respective_permissions(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $item = $this->makeItem($boq, ['ai_suggested_rate' => 120]);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('reviewUsingSuggested', $item->id)
            ->assertForbidden();

        $this->grant($user, 'boq.edit');
        $item->update(['reviewed_rate' => 120, 'status' => 'reviewed']);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('approveItem', $item->id)
            ->assertForbidden();
    }

    public function test_actions_cannot_target_an_item_from_another_boq(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $this->grant($user, 'boq.edit');
        [, , $otherBoq] = $this->makeBoq();
        $otherItem = $this->makeItem($otherBoq, ['ai_suggested_rate' => 90]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('reviewUsingSuggested', $otherItem->id);
    }

    public function test_users_cannot_mount_another_tenants_boq(): void
    {
        [, $user] = $this->makeBoq();
        [, , $otherBoq] = $this->makeBoq();
        $otherItem = $this->makeItem($otherBoq, ['ai_suggested_rate' => 90]);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $otherBoq])
            ->assertForbidden();

        $this->assertSame('pending', $otherItem->fresh()->status);
    }

    public function test_manual_review_requires_a_nonnegative_rate_and_does_not_change_amount(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $this->grant($user, 'boq.edit');
        $item = $this->makeItem($boq, ['quantity' => 3, 'amount' => 150]);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->set('manualRate', '-1')
            ->call('reviewItem', $item->id)
            ->assertHasErrors(['manualRate' => 'min'])
            ->set('manualRate', '75.50')
            ->set('reviewNotes', 'Supplier quote checked')
            ->call('reviewItem', $item->id)
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('75.50', $item->reviewed_rate);
        $this->assertNull($item->approved_rate);
        $this->assertSame('150.00', $item->amount);
        $this->assertSame('reviewed', $item->status);
        $this->assertSame($user->id, $item->reviewed_by);
        $this->assertSame('Supplier quote checked', $item->notes);
        $this->assertSame('under_review', $boq->fresh()->status);
    }

    public function test_suggested_rate_can_be_reviewed_then_approved_with_auditable_amount_update(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $this->grant($user, 'boq.edit');
        $this->grant($user, 'boq.approve');
        $item = $this->makeItem($boq, [
            'quantity' => 2.5,
            'ai_suggested_rate' => 80,
            'amount' => 125,
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('reviewUsingSuggested', $item->id)
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('80.00', $item->reviewed_rate);
        $this->assertNull($item->approved_rate);
        $this->assertSame('125.00', $item->amount);
        $this->assertNotNull($item->reviewed_at);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('approveItem', $item->id)
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('80.00', $item->approved_rate);
        $this->assertSame('200.00', $item->amount);
        $this->assertSame('approved', $item->status);
        $this->assertSame($user->id, $item->approved_by);
        $this->assertNotNull($item->approved_at);
        $this->assertSame('approved', $boq->fresh()->status);
    }

    public function test_rejection_requires_a_reason_and_records_the_reviewer(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $this->grant($user, 'boq.edit');
        $item = $this->makeItem($boq, ['ai_suggested_rate' => 80]);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->set('rejectionReason', '')
            ->call('rejectItem', $item->id)
            ->assertHasErrors(['rejectionReason' => 'required'])
            ->set('rejectionReason', 'The source specification is incompatible.')
            ->call('rejectItem', $item->id)
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('rejected', $item->status);
        $this->assertSame($user->id, $item->rejected_by);
        $this->assertNotNull($item->rejected_at);
        $this->assertSame('The source specification is incompatible.', $item->rejection_reason);
        $this->assertNull($item->approved_rate);
        $this->assertSame('under_review', $boq->fresh()->status);
    }

    public function test_approve_all_only_approves_reviewed_items_and_completes_when_every_item_is_approved(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $this->grant($user, 'boq.edit');
        $this->grant($user, 'boq.approve');
        $reviewedOne = $this->makeItem($boq, ['quantity' => 2, 'reviewed_rate' => 25, 'status' => 'reviewed']);
        $reviewedTwo = $this->makeItem($boq, ['quantity' => 3, 'reviewed_rate' => 40, 'status' => 'reviewed']);
        $pending = $this->makeItem($boq, ['quantity' => 4, 'amount' => 200]);

        $component = Livewire::actingAs($user)->test(Show::class, ['boq' => $boq]);
        $component->call('approveAllReviewed')->assertHasNoErrors();

        $this->assertSame('50.00', $reviewedOne->fresh()->amount);
        $this->assertSame('120.00', $reviewedTwo->fresh()->amount);
        $this->assertNull($pending->fresh()->approved_rate);
        $this->assertSame('200.00', $pending->fresh()->amount);
        $this->assertSame('under_review', $boq->fresh()->status);

        $component
            ->set('manualRate', '60')
            ->call('reviewItem', $pending->id)
            ->call('approveAllReviewed')
            ->assertHasNoErrors();

        $this->assertSame('60.00', $pending->fresh()->approved_rate);
        $this->assertSame('240.00', $pending->fresh()->amount);
        $this->assertSame('approved', $boq->fresh()->status);
    }

    /** @return array{Organisation, User, Boq} */
    private function makeBoq(): array
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
        ]);
        $boq = Boq::factory()->create([
            'organisation_id' => $organisation->id,
            'project_id' => $project->id,
            'status' => 'under_review',
        ]);

        return [$organisation, $user, $boq];
    }

    private function makeItem(Boq $boq, array $attributes = []): BoqItem
    {
        return BoqItem::factory()->create(array_merge([
            'boq_id' => $boq->id,
            'quantity' => 1,
            'original_rate' => 50,
            'ai_suggested_rate' => null,
            'reviewed_rate' => null,
            'approved_rate' => null,
            'amount' => 50,
            'status' => 'pending',
        ], $attributes));
    }

    private function grant(User $user, string $slug): void
    {
        $permission = Permission::factory()->create([
            'name' => $slug,
            'slug' => $slug,
            'module' => 'boq',
        ]);
        $user->permissions()->attach($permission);
    }
}
