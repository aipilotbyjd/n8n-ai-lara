<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
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
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],
            'team' => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'slug' => $this->team->slug,
                'color' => $this->team->color,
            ] : null,
            'user' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayAttribute(),
            'is_active' => $this->is_active,
            'is_template' => $this->is_template,
            'version' => $this->version,
            'tags' => $this->tags ?? [],
            'settings' => $this->settings,
            'last_executed_at' => $this->last_executed_at,
            'execution_count' => $this->execution_count,

            // Workflow structure info (without exposing full workflow data for security)
            'structure' => [
                'nodes_count' => $this->getNodesCount(),
                'connections_count' => $this->getConnectionsCount(),
            ],

            // Permissions
            'can_view' => $user ? $this->canBeViewedBy($user) : false,
            'can_edit' => $user ? $this->canBeEditedBy($user) : false,
            'can_delete' => $user ? $this->canBeDeletedBy($user) : false,
            'can_execute' => $user ? $this->canBeExecutedBy($user) : false,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships (conditionally loaded)
            'latest_execution' => new ExecutionResource($this->whenLoaded('latestExecution')),
            'recent_executions' => ExecutionResource::collection($this->whenLoaded('executions')),
        ];
    }
}
