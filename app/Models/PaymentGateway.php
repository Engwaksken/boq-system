<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'driver',
        'description',
        'config',
        'supported_currencies',
        'supported_countries',
        'supported_methods',
        'is_active',
        'is_test_mode',
        'webhook_url',
        'payment_timeout_seconds',
        'refund_settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'supported_currencies' => 'array',
            'supported_countries' => 'array',
            'supported_methods' => 'array',
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
            'refund_settings' => 'array',
        ];
    }

    /**
     * Transactions processed through this gateway.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
