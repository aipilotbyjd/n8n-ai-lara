<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
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
            'color' => $this->color,
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],
            'owner' => new UserResource($this->whenLoaded('owner')),
            'role' => $user ? $user->getTeamRole($this->resource) : null,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'stats' => $this->when($this->resource->relationLoaded('users'), function () {
                return [
                    'members_count' => $this->getMembersCount(),
                    'workflows_count' => $this->getWorkflowsCount(),
                ];
            }),
            'can_manage' => $user ? $user->canManageTeam($this->resource) : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships (conditionally loaded)
            'members' => UserResource::collection($this->whenLoaded('users')),
            'recent_workflows' => WorkflowResource::collection($this->whenLoaded('workflows')),
        ];
    }
}
