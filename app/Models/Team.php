<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'organization_id',
        'owner_id',
        'color',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the organization that owns this team
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the team owner
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all users in this team
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_users')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get all workflows owned by this team
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    /**
     * Check if user is team owner
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Check if user is team admin
     */
    public function isAdmin(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)
            ->where('role', 'admin')->exists() || $this->isOwner($user);
    }

    /**
     * Check if user is team member
     */
    public function isMember(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists() || $this->isOwner($user);
    }

    /**
     * Get team members count
     */
    public function getMembersCount(): int
    {
        return $this->users()->count() + 1; // +1 for owner
    }

    /**
     * Get team workflows count
     */
    public function getWorkflowsCount(): int
    {
        return $this->workflows()->count();
    }
}
