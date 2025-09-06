<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Get user's role
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * Get all organizations owned by this user
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * Get all organizations this user belongs to
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get all teams owned by this user
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Get all teams this user belongs to
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_users')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get all workflows created by this user
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    /**
     * Get all credentials owned by this user
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    /**
     * Get all executions by this user
     */
    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    /**
     * Get user's role in a specific organization
     */
    public function getOrganizationRole(Organization $organization): ?string
    {
        if ($organization->isOwner($this)) {
            return 'owner';
        }

        $pivot = $this->organizations()->where('organization_id', $organization->id)->first()?->pivot;
        return $pivot?->role;
    }

    /**
     * Get user's role in a specific team
     */
    public function getTeamRole(Team $team): ?string
    {
        if ($team->isOwner($this)) {
            return 'owner';
        }

        $pivot = $this->teams()->where('team_id', $team->id)->first()?->pivot;
        return $pivot?->role;
    }

    /**
     * Check if user can manage organization
     */
    public function canManageOrganization(Organization $organization): bool
    {
        return $organization->isOwner($this) || $organization->isAdmin($this);
    }

    /**
     * Check if user can manage team
     */
    public function canManageTeam(Team $team): bool
    {
        return $team->isOwner($this) || $team->isAdmin($this) ||
               $team->organization->isOwner($this) || $team->organization->isAdmin($this);
    }
}
