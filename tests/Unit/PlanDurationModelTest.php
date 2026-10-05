<?php

namespace Tests\Unit;

use App\Models\Plan;
use PHPUnit\Framework\Attributes\DataProvider;

final class PlanDurationModelTest extends IsolatedPlanTestCase
{
    public function test_duration_hours_is_mass_assignable(): void
    {
        $plan = Plan::factory()->make();
        $plan->fill(['duration_hours' => 48]);

        $this->assertSame(48, $plan->getAttributes()['duration_hours']);
    }

    #[DataProvider('durationValues')]
    public function test_duration_attributes_cast_without_changing_nulls(string $attribute, mixed $input, ?int $expected): void
    {
        $plan = Plan::factory()->make([$attribute => $input]);

        $this->assertSame($expected, $plan->{$attribute});
    }

    public static function durationValues(): iterable
    {
        foreach (['duration_days', 'duration_hours'] as $attribute) {
            yield $attribute.' numeric string' => [$attribute, '48', 48];
            yield $attribute.' zero' => [$attribute, '0', 0];
            yield $attribute.' null' => [$attribute, null, null];
        }
    }

    public function test_default_factory_retains_day_based_duration(): void
    {
        $plan = Plan::factory()->make();

        $this->assertSame([30, null], [$plan->duration_days, $plan->duration_hours]);
    }

    public function test_hours_factory_defaults_to_24_hours_and_clears_days(): void
    {
        $plan = Plan::factory()->hours()->make();

        $this->assertSame([null, 24], [$plan->duration_days, $plan->duration_hours]);
    }

    public function test_hours_factory_overrides_an_existing_day_duration(): void
    {
        $plan = Plan::factory()->state(['duration_days' => 90])->hours(48)->make();

        $this->assertSame([null, 48], [$plan->duration_days, $plan->duration_hours]);
    }
}
