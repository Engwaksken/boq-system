<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BoqPricingJob extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'boq_id',
        'user_id',
        'organisation_id',
        'location',
        'status',
        'current_batch',
        'total_batches',
        'batch_size',
        'total_items',
        'processed_items',
        'failed_items',
        'locked_at',
        'locked_by',
        'started_at',
        'completed_at',
        'cancelled_at',
        'error_message',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'current_batch' => 'integer',
            'total_batches' => 'integer',
            'batch_size' => 'integer',
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'failed_items' => 'integer',
            'locked_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The BOQ this pricing job belongs to.
     */
    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    /**
     * The user who initiated this pricing job.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organisation this pricing job belongs to.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The user who currently has this job locked.
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Items associated with this pricing job.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class, 'pricing_job_id');
    }

    /**
     * Scope: Only active (non-terminal) jobs.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'queued',
            'processing',
            'paused',
        ]);
    }

    /**
     * Scope: Jobs for a specific BOQ.
     */
    public function scopeForBoq($query, int $boqId)
    {
        return $query->where('boq_id', $boqId);
    }

    /**
     * Scope: Jobs for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Jobs that are currently locked.
     */
    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at')
            ->whereNotNull('locked_by');
    }

    /**
     * Scope: Jobs that are not locked.
     */
    public function scopeUnlocked($query)
    {
        return $query->whereNull('locked_at')
            ->whereNull('locked_by');
    }

    /**
     * Check if the job is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null && $this->locked_by !== null;
    }

    /**
     * Lock the job for a specific user.
     */
    public function lock(User $user): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $this->update([
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);

        return true;
    }

    /**
     * Unlock the job.
     */
    public function unlock(): void
    {
        $this->update([
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    /**
     * Check if the job can process the next batch.
     */
    public function canProcessNextBatch(): bool
    {
        return $this->status === 'processing'
            && $this->current_batch < $this->total_batches
            && !$this->isLocked();
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_items === 0) {
            return 0.0;
        }

        return round(($this->processed_items / $this->total_items) * 100, 2);
    }

    /**
     * Mark a batch as complete and update counters.
     */
    public function markBatchComplete(int $batchNumber, int $processed, int $failed): void
    {
        DB::transaction(function () use ($processed, $failed) {
            $locked = static::lockForUpdate()->find($this->id);
            if ($locked) {
                $locked->increment('processed_items', $processed);
                $locked->increment('failed_items', $failed);
                $locked->increment('current_batch');
                $locked->refresh();

                if ($locked->processed_items + $locked->failed_items >= $locked->total_items) {
                    $locked->complete();
                }
            }
        });
    }

    /**
     * Mark the job as completed successfully.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_batch' => $this->total_batches,
        ]);
    }

    /**
     * Mark the job as failed with an error message.
     */
    public function fail(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}