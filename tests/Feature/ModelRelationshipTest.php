<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_organisation(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => $organisation->id])->save();

        $this->assertInstanceOf(Organisation::class, $user->organisation);
        $this->assertEquals($organisation->id, $user->organisation->id);
    }

    public function test_user_has_many_subscriptions(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id])->save();

        $this->assertCount(1, $user->subscriptions);
        $this->assertInstanceOf(Subscription::class, $user->subscriptions->first());
    }

    public function test_user_roles_and_permissions_relationships(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $user->roles()->attach($role);
        $role->permissions()->attach($permission);

        $this->assertTrue($user->roles->contains($role));
        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($user->hasRole($role->slug));
        $this->assertTrue($user->hasPermission($permission->slug));
    }

    public function test_plan_belongs_to_many_features(): void
    {
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->create();

        $plan->features()->attach($feature);

        $this->assertTrue($plan->features->contains($feature));
        $this->assertTrue($plan->includesFeature($feature->code));
    }

    public function test_subscription_belongs_to_plan_and_user(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertInstanceOf(Plan::class, $subscription->plan);
        $this->assertInstanceOf(User::class, $subscription->user);
        $this->assertEquals($plan->id, $subscription->plan->id);
    }

    public function test_subscription_has_many_entitlements(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill(['user_id' => $user->id])->save();

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertCount(1, $subscription->entitlements);
        $this->assertInstanceOf(Entitlement::class, $subscription->entitlements->first());
    }

    public function test_project_has_many_boqs(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
        ]);
        Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $organisation->id]);

        $this->assertCount(1, $project->boqs);
        $this->assertInstanceOf(Boq::class, $project->boqs->first());
    }

    public function test_boq_has_many_items(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
        ]);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $organisation->id]);
        BoqItem::factory()->create(['boq_id' => $boq->id]);

        $this->assertCount(1, $boq->items);
        $this->assertInstanceOf(BoqItem::class, $boq->items->first());
    }

    public function test_boq_item_recalculates_amount(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
        ]);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $organisation->id]);
        $item = BoqItem::factory()->create([
            'boq_id' => $boq->id,
            'quantity' => 10,
            'approved_rate' => 5000,
        ]);

        $item->recalculateAmount();

        $this->assertEquals(50000, (float) $item->amount);
    }
}
