<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_display' => $this->getTypeDisplayAttribute(),
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],
            'user' => new UserResource($this->whenLoaded('user')),
            'is_shared' => $this->is_shared,
            'expires_at' => $this->expires_at,
            'last_used_at' => $this->last_used_at,
            'usage_count' => $this->usage_count,

            // Status information
            'is_expired' => $this->isExpired(),
            'is_active' => $this->isActive(),

            // Permissions
            'can_view' => $user ? $this->canBeViewedBy($user) : false,
            'can_edit' => $user ? $this->canBeEditedBy($user) : false,
            'can_delete' => $user ? $this->canBeDeletedBy($user) : false,
            'can_test' => $user ? $this->canBeViewedBy($user) : false,
            'can_share' => $user ? $this->canBeEditedBy($user) && $this->organization->hasActiveSubscription() : false,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Note: Actual credential data is NEVER exposed in API responses for security
            // Use separate endpoints for credential operations that require authentication
        ];
    }
}
