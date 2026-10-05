<?php

namespace Tests\Unit;

use App\Http\Resources\ProxySubscriptionResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

final class SubscriptionDurationResourceTest extends IsolatedPlanTestCase
{
    #[DataProvider('resourceDurations')]
    public function test_loaded_plan_serializes_duration_hours(string $resourceClass, bool $proxy, ?string $hours, ?int $expected): void
    {
        $subscription = $this->subscription($proxy);
        $subscription->setRelation('plan', Plan::factory()->make([
            'duration_days' => $hours === null ? 30 : null,
            'duration_hours' => $hours,
        ]));

        $payload = (new $resourceClass($subscription))->resolve(Request::create('/api/subscriptions', 'GET'));
        $json = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('duration_hours', $json['plan']);
        $this->assertSame($expected, $json['plan']['duration_hours']);
    }

    public static function resourceDurations(): iterable
    {
        foreach ([SubscriptionResource::class => false, ProxySubscriptionResource::class => true] as $class => $proxy) {
            yield $class.' hours' => [$class, $proxy, '48', 48];
            yield $class.' day-based null hours' => [$class, $proxy, null, null];
            yield $class.' zero hours' => [$class, $proxy, '0', 0];
        }
    }

    #[DataProvider('resourceClasses')]
    public function test_unloaded_plan_is_omitted_without_querying(string $resourceClass, bool $proxy): void
    {
        $payload = (new $resourceClass($this->subscription($proxy)))->resolve(Request::create('/api/subscriptions', 'GET'));

        $this->assertArrayNotHasKey('plan', $payload);
    }

    public static function resourceClasses(): iterable
    {
        yield 'self' => [SubscriptionResource::class, false];
        yield 'proxy' => [ProxySubscriptionResource::class, true];
    }

    private function subscription(bool $proxy): Subscription
    {
        // Override nested factories and callback inputs: even make() otherwise creates users/plans.
        return Subscription::factory()->make([
            'plan_id' => 1,
            'user_id' => 1,
            'organisation_id' => 1,
            'payer_id' => $proxy ? 2 : null,
            'beneficiary_id' => $proxy ? 1 : null,
            'start_date' => null,
            'end_date' => null,
            'renewal_date' => null,
        ]);
    }
}
