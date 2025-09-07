<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'organization_id',
        'team_id',
        'user_id',
        'workflow_data',
        'settings',
        'status',
        'is_active',
        'is_template',
        'version',
        'tags',
        'last_executed_at',
        'execution_count',
    ];

    protected $casts = [
        'workflow_data' => 'array',
        'settings' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'is_template' => 'boolean',
        'version' => 'integer',
        'execution_count' => 'integer',
        'last_executed_at' => 'datetime',
    ];

    /**
     * Get the organization that owns this workflow
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the team that owns this workflow
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created this workflow
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all executions for this workflow
     */
    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class)->latest();
    }

    /**
     * Get executions with optimized loading
     */
    public function executionsOptimized(): HasMany
    {
        return $this->hasMany(Execution::class)
            ->select(['id', 'workflow_id', 'status', 'started_at', 'finished_at', 'duration'])
            ->latest()
            ->limit(10);
    }

    /**
     * Get all tags associated with this workflow
     */
    public function workflowTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'workflow_tags');
    }

    /**
     * Get the latest execution
     */
    public function latestExecution()
    {
        return $this->hasOne(Execution::class)->latestOfMany();
    }

    /**
     * Check if workflow is owned by user
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Check if workflow belongs to user's organization
     */
    public function belongsToUserOrganization(User $user): bool
    {
        return $this->organization->isMember($user);
    }

    /**
     * Check if workflow belongs to user's team
     */
    public function belongsToUserTeam(User $user): bool
    {
        return $this->team && $this->team->isMember($user);
    }

    /**
     * Check if user can view this workflow
     */
    public function canBeViewedBy(User $user): bool
    {
        return $this->isOwnedBy($user) ||
               $this->belongsToUserOrganization($user) ||
               $this->belongsToUserTeam($user);
    }

    /**
     * Check if user can edit this workflow
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->isOwnedBy($user) ||
               ($this->belongsToUserOrganization($user) && $this->organization->isAdmin($user)) ||
               ($this->belongsToUserTeam($user) && $this->team->isAdmin($user));
    }

    /**
     * Check if user can delete this workflow
     */
    public function canBeDeletedBy(User $user): bool
    {
        return $this->isOwnedBy($user) ||
               ($this->belongsToUserOrganization($user) && $this->organization->isAdmin($user)) ||
               ($this->belongsToUserTeam($user) && $this->team->isAdmin($user));
    }

    /**
     * Check if user can execute this workflow
     */
    public function canBeExecutedBy(User $user): bool
    {
        return $this->canBeViewedBy($user) && $this->is_active;
    }

    /**
     * Get workflow status display
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
            default => 'Unknown'
        };
    }

    /**
     * Get workflow nodes count
     */
    public function getNodesCount(): int
    {
        return count($this->workflow_data['nodes'] ?? []);
    }

    /**
     * Get workflow connections count
     */
    public function getConnectionsCount(): int
    {
        return count($this->workflow_data['connections'] ?? []);
    }

    /**
     * Increment execution count
     */
    public function incrementExecutionCount(): void
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }

    /**
     * Scope for active workflows
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for user's workflows
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('organization', function ($orgQuery) use ($user) {
                  $orgQuery->whereHas('users', function ($userQuery) use ($user) {
                      $userQuery->where('users.id', $user->id);
                  });
              })
              ->orWhereHas('team', function ($teamQuery) use ($user) {
                  $teamQuery->whereHas('users', function ($userQuery) use ($user) {
                      $userQuery->where('users.id', $user->id);
                  });
              });
        });
    }

    /**
     * Scope for templates
     */
    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }

    /**
     * Scope for organization workflows
     */
    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    /**
     * Scope for team workflows
     */
    public function scopeForTeam($query, Team $team)
    {
        return $query->where('team_id', $team->id);
    }
}
