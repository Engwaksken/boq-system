<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\BoqPricingJob;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqPricingJob>
 */
class BoqPricingJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BoqPricingJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boq_id' => Boq::factory(),
            'user_id' => User::factory(),
            'organisation_id' => Organisation::factory(),
            'location' => fake()->city(),
            'status' => fake()->randomElement([
                'queued',
                'processing',
                'paused',
                'completed',
                'completed_with_errors',
                'failed',
                'cancelled',
            ]),
            'current_batch' => fake()->numberBetween(0, 10),
            'total_batches' => fake()->numberBetween(1, 10),
            'batch_size' => fake()->numberBetween(10, 50),
            'total_items' => fake()->numberBetween(0, 500),
            'processed_items' => fake()->numberBetween(0, 500),
            'failed_items' => fake()->numberBetween(0, 50),
            'locked_at' => null,
            'locked_by' => null,
            'started_at' => fake()->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'completed_at' => null,
            'cancelled_at' => null,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the job is queued.
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'current_batch' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the job is currently processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => $attributes['started_at'] ?? now(),
        ]);
    }

    /**
     * Indicate that the job is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }

    /**
     * Indicate that the job completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'current_batch' => $attributes['total_batches'],
            'processed_items' => $attributes['total_items'],
            'failed_items' => 0,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the job completed with errors.
     */
    public function completedWithErrors(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed_with_errors',
            'current_batch' => $attributes['total_batches'],
            'failed_items' => fake()->numberBetween(1, $attributes['total_items'] ?? 10),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the job failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the job was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Indicate that the job is currently locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_at' => now(),
            'locked_by' => User::factory(),
        ]);
    }

    /**
     * Set a specific BOQ for the job.
     */
    public function forBoq(Boq $boq): static
    {
        return $this->state(fn (array $attributes) => [
            'boq_id' => $boq->id,
            'organisation_id' => $boq->organisation_id,
        ]);
    }

    /**
     * Set a specific user for the job.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);
    }
}