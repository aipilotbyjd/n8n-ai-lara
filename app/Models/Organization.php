<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_id',
        'settings',
        'is_active',
        'subscription_status',
        'subscription_plan',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * Get the owner of the organization
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all teams in this organization
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get all users in this organization
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get all workflows in this organization
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    /**
     * Get all credentials in this organization
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    /**
     * Get all executions in this organization
     */
    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    /**
     * Check if user is owner
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)
            ->where('role', 'admin')->exists() || $this->isOwner($user);
    }

    /**
     * Check if user is member
     */
    public function isMember(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists() || $this->isOwner($user);
    }

    /**
     * Check if organization has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        if ($this->subscription_status === 'active') {
            return true;
        }

        if ($this->trial_ends_at && $this->trial_ends_at->isFuture()) {
            return true;
        }

        return false;
    }

    /**
     * Get organization members count
     */
    public function getMembersCount(): int
    {
        return $this->users()->count() + 1; // +1 for owner
    }

    /**
     * Get organization teams count
     */
    public function getTeamsCount(): int
    {
        return $this->teams()->count();
    }

    /**
     * Get organization workflows count
     */
    public function getWorkflowsCount(): int
    {
        return $this->workflows()->count();
    }
}
