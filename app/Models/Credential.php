<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Credential extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'organization_id',
        'user_id',
        'data',
        'is_shared',
        'expires_at',
        'last_used_at',
        'usage_count',
    ];

    protected $casts = [
        'data' => 'encrypted:array',
        'is_shared' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    protected $hidden = [
        'data',
    ];

    /**
     * Get the organization that owns this credential
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this credential
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if credential is owned by user
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Check if credential belongs to user's organization
     */
    public function belongsToUserOrganization(User $user): bool
    {
        return $this->organization->isMember($user);
    }

    /**
     * Check if user can view this credential
     */
    public function canBeViewedBy(User $user): bool
    {
        return $this->isOwnedBy($user) ||
               ($this->belongsToUserOrganization($user) && $this->is_shared) ||
               $this->organization->isAdmin($user);
    }

    /**
     * Check if user can edit this credential
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->isOwnedBy($user) ||
               $this->organization->isAdmin($user);
    }

    /**
     * Check if user can delete this credential
     */
    public function canBeDeletedBy(User $user): bool
    {
        return $this->isOwnedBy($user) ||
               $this->organization->isAdmin($user);
    }

    /**
     * Check if credential is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if credential is active
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get decrypted credential data
     */
    public function getDecryptedData(): array
    {
        return Crypt::decrypt($this->data);
    }

    /**
     * Set encrypted credential data
     */
    public function setDecryptedData(array $data): void
    {
        $this->data = Crypt::encrypt($data);
    }

    /**
     * Get a specific field from credential data
     */
    public function getCredentialField(string $field)
    {
        $data = $this->getDecryptedData();
        return $data[$field] ?? null;
    }

    /**
     * Update usage statistics
     */
    public function updateUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get credential type display name
     */
    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'oauth2' => 'OAuth 2.0',
            'api_key' => 'API Key',
            'basic_auth' => 'Basic Auth',
            'bearer_token' => 'Bearer Token',
            'custom' => 'Custom',
            default => ucfirst(str_replace('_', ' ', $this->type))
        };
    }

    /**
     * Scope for active credentials
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for expired credentials
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for shared credentials
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope for user's credentials
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($orgQuery) use ($user) {
                  $orgQuery->where('is_shared', true)
                           ->whereHas('organization', function ($organizationQuery) use ($user) {
                               $organizationQuery->whereHas('users', function ($userQuery) use ($user) {
                                   $userQuery->where('users.id', $user->id);
                               });
                           });
              });
        });
    }

    /**
     * Scope for organization credentials
     */
    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    /**
     * Scope by credential type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
