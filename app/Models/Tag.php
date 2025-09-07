<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'organization_id',
        'user_id',
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string',
        'color' => 'string',
    ];

    /**
     * Get the organization that owns this tag
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this tag
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all workflows associated with this tag
     */
    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'workflow_tags');
    }

    /**
     * Scope for tags belonging to a specific organization
     */
    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    /**
     * Scope for tags created by a specific user
     */
    public function scopeCreatedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Get tag display name with color
     */
    public function getDisplayAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get tag badge style
     */
    public function getBadgeStyleAttribute(): string
    {
        return $this->color ? "background-color: {$this->color}" : '';
    }
}
