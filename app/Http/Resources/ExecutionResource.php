<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionResource extends JsonResource
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
            'execution_id' => $this->execution_id,
            'workflow' => [
                'id' => $this->workflow->id,
                'name' => $this->workflow->name,
                'slug' => $this->workflow->slug,
            ],
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],
            'user' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayAttribute(),
            'mode' => $this->mode,
            'mode_display' => $this->getModeDisplayAttribute(),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration' => $this->duration,
            'duration_formatted' => $this->duration ? $this->formatDuration($this->duration) : null,
            'retry_count' => $this->retry_count,
            'max_retries' => $this->max_retries,

            // Execution data (conditionally exposed based on permissions)
            'input_data' => $this->when($user && $this->workflow->canBeViewedBy($user), $this->input_data),
            'output_data' => $this->when($user && $this->workflow->canBeViewedBy($user), $this->output_data),
            'error_message' => $this->error_message,

            // Status flags
            'is_running' => $this->isRunning(),
            'is_completed' => $this->isCompleted(),
            'is_successful' => $this->isSuccessful(),
            'has_failed' => $this->hasFailed(),
            'is_canceled' => $this->isCanceled(),
            'can_be_retried' => $this->canBeRetried(),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Format duration in milliseconds to human readable format
     */
    private function formatDuration(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return $milliseconds . 'ms';
        }

        $seconds = $milliseconds / 1000;

        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }

        $minutes = $seconds / 60;

        if ($minutes < 60) {
            return round($minutes, 2) . 'm';
        }

        $hours = $minutes / 60;
        return round($hours, 2) . 'h';
    }
}
