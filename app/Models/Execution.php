<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Execution extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'organization_id',
        'user_id',
        'execution_id',
        'status',
        'mode',
        'started_at',
        'finished_at',
        'duration',
        'input_data',
        'output_data',
        'error_message',
        'retry_count',
        'max_retries',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration' => 'integer',
        'input_data' => 'array',
        'output_data' => 'array',
        'metadata' => 'array',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    /**
     * Get the workflow this execution belongs to
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the organization this execution belongs to
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who triggered this execution
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all execution logs
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class);
    }

    /**
     * Check if execution is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if execution is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['success', 'error', 'canceled']);
    }

    /**
     * Check if execution was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if execution failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Check if execution was canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Check if execution can be retried
     */
    public function canBeRetried(): bool
    {
        return $this->hasFailed() && $this->retry_count < $this->max_retries;
    }

    /**
     * Get execution status display
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'waiting' => 'Waiting',
            'running' => 'Running',
            'success' => 'Success',
            'error' => 'Error',
            'canceled' => 'Canceled',
            default => 'Unknown'
        };
    }

    /**
     * Get execution mode display
     */
    public function getModeDisplayAttribute(): string
    {
        return match($this->mode) {
            'manual' => 'Manual',
            'webhook' => 'Webhook',
            'schedule' => 'Schedule',
            'api' => 'API',
            default => 'Unknown'
        };
    }

    /**
     * Calculate and set duration
     */
    public function calculateDuration(): void
    {
        if ($this->started_at && $this->finished_at) {
            $this->duration = $this->started_at->diffInMilliseconds($this->finished_at);
            $this->save();
        }
    }

    /**
     * Mark execution as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark execution as completed
     */
    public function markAsCompleted(array $outputData = null): void
    {
        $this->update([
            'status' => 'success',
            'finished_at' => now(),
            'output_data' => $outputData,
        ]);
        $this->calculateDuration();
    }

    /**
     * Mark execution as failed
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'error',
            'finished_at' => now(),
            'error_message' => $errorMessage,
        ]);
        $this->calculateDuration();
    }

    /**
     * Mark execution as canceled
     */
    public function markAsCanceled(): void
    {
        $this->update([
            'status' => 'canceled',
            'finished_at' => now(),
        ]);
        $this->calculateDuration();
    }

    /**
     * Retry execution
     */
    public function retry(): bool
    {
        if (!$this->canBeRetried()) {
            return false;
        }

        // Create new execution with incremented retry count
        $newExecution = self::create([
            'workflow_id' => $this->workflow_id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'execution_id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'waiting',
            'mode' => $this->mode,
            'input_data' => $this->input_data,
            'retry_count' => $this->retry_count + 1,
            'max_retries' => $this->max_retries,
            'metadata' => $this->metadata,
        ]);

        return true;
    }

    /**
     * Scope for running executions
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for completed executions
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['success', 'error', 'canceled']);
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'error');
    }

    /**
     * Scope for successful executions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for workflow executions
     */
    public function scopeForWorkflow($query, Workflow $workflow)
    {
        return $query->where('workflow_id', $workflow->id);
    }

    /**
     * Scope for organization executions
     */
    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('organization_id', $organization->id);
    }

    /**
     * Scope for user executions
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope for recent executions
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
