<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'role' => $user ? $user->getOrganizationRole($this->resource) : null,
            'is_active' => $this->is_active,
            'subscription_status' => $this->subscription_status,
            'subscription_plan' => $this->subscription_plan,
            'trial_ends_at' => $this->trial_ends_at,
            'subscription_ends_at' => $this->subscription_ends_at,
            'settings' => $this->settings,
            'stats' => $this->when($this->resource->relationLoaded('users') || $this->resource->relationLoaded('teams'), function () {
                return [
                    'members_count' => $this->users()->count() + 1, // +1 for owner
                    'teams_count' => $this->teams()->count(),
                    'workflows_count' => $this->workflows()->count(),
                    'credentials_count' => $this->credentials()->count(),
                    'executions_count' => $this->executions()->count(),
                ];
            }),
            'has_active_subscription' => $this->hasActiveSubscription(),
            'can_manage' => $user ? $user->canManageOrganization($this->resource) : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships (conditionally loaded)
            'members' => UserResource::collection($this->whenLoaded('users')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'recent_workflows' => WorkflowResource::collection($this->whenLoaded('workflows')),
        ];
    }
}
