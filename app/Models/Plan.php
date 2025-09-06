<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'stripe_plan_id',
        'price',
        'currency',
        'interval',
        'interval_count',
        'trial_days',
        'features',
        'limits',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_days' => 'integer',
        'features' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all subscriptions for this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get price per month (for yearly plans)
     */
    public function getPricePerMonthAttribute(): float
    {
        if ($this->interval === 'year') {
            return round($this->price / 12, 2);
        }

        return $this->price;
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get limit for a specific resource
     */
    public function getLimit(string $resource): ?int
    {
        return $this->limits[$resource] ?? null;
    }

    /**
     * Check if plan allows unlimited resource
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === null || $this->getLimit($resource) === -1;
    }

    /**
     * Get plan interval display name
     */
    public function getIntervalDisplayAttribute(): string
    {
        if ($this->interval === 'month') {
            return $this->interval_count === 1 ? 'Monthly' : "{$this->interval_count} Months";
        }

        if ($this->interval === 'year') {
            return $this->interval_count === 1 ? 'Yearly' : "{$this->interval_count} Years";
        }

        return ucfirst($this->interval);
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for free plans
     */
    public function scopeFree($query)
    {
        return $query->where('price', 0);
    }

    /**
     * Scope for paid plans
     */
    public function scopePaid($query)
    {
        return $query->where('price', '>', 0);
    }
}
