<?php

namespace App\Jobs;

use App\Models\Execution;
use App\Models\Workflow;
use App\Workflow\Engine\WorkflowExecutionEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWorkflowExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 3600; // 1 hour timeout
    public int $backoff = 60; // 1 minute delay between retries

    protected Workflow $workflow;
    protected array $triggerData;
    protected ?int $executionId;
    protected string $priority;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Workflow $workflow,
        array $triggerData = [],
        ?int $executionId = null,
        string $priority = 'normal'
    ) {
        $this->workflow = $workflow;
        $this->triggerData = $triggerData;
        $this->executionId = $executionId;
        $this->priority = $priority;

        // Set queue based on priority
        $this->onQueue($this->getQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowExecutionEngine $engine): void
    {
        $startTime = microtime(true);

        Log::info('Starting workflow execution job', [
            'workflow_id' => $this->workflow->id,
            'execution_id' => $this->executionId,
            'priority' => $this->priority,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Execute the workflow
            $result = $engine->executeWorkflow($this->workflow, $this->triggerData);

            $executionTime = microtime(true) - $startTime;

            Log::info('Workflow execution completed successfully', [
                'workflow_id' => $this->workflow->id,
                'execution_id' => $this->executionId,
                'execution_time' => $executionTime,
                'success' => $result->isSuccess(),
            ]);

            // Update execution record if it exists
            if ($this->executionId) {
                $execution = Execution::find($this->executionId);
                if ($execution) {
                    $execution->update([
                        'status' => $result->isSuccess() ? 'success' : 'error',
                        'finished_at' => now(),
                        'output_data' => $result->getOutputData(),
                        'error_message' => $result->getErrorMessage(),
                    ]);
                }
            }

            // Trigger success callbacks
            $this->handleSuccess($result, $executionTime);

        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('Workflow execution job failed', [
                'workflow_id' => $this->workflow->id,
                'execution_id' => $this->executionId,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
                'attempt' => $this->attempts(),
            ]);

            // Update execution record with error
            if ($this->executionId) {
                $execution = Execution::find($this->executionId);
                if ($execution) {
                    $execution->update([
                        'status' => 'error',
                        'finished_at' => now(),
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            // Handle failure
            $this->handleFailure($e, $executionTime);

            throw $e; // Re-throw to trigger retry logic
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Workflow execution job failed permanently', [
            'workflow_id' => $this->workflow->id,
            'execution_id' => $this->executionId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update execution record as permanently failed
        if ($this->executionId) {
            $execution = Execution::find($this->executionId);
            if ($execution) {
                $execution->update([
                    'status' => 'error',
                    'finished_at' => now(),
                    'error_message' => 'Job failed after ' . $this->attempts() . ' attempts: ' . $exception->getMessage(),
                ]);
            }
        }

        // Send failure notifications
        $this->sendFailureNotification($exception);
    }

    /**
     * Get the queue name based on priority.
     */
    protected function getQueueName(): string
    {
        return match ($this->priority) {
            'high' => 'high-priority',
            'low' => 'low-priority',
            default => 'default',
        };
    }

    /**
     * Handle successful execution.
     */
    protected function handleSuccess($result, float $executionTime): void
    {
        // Log success metrics
        $this->logExecutionMetrics('success', $executionTime, $result);

        // Trigger webhooks or callbacks if configured
        $this->triggerSuccessCallbacks($result);

        // Update workflow statistics
        $this->updateWorkflowStatistics($executionTime, true);

        // Clean up temporary resources
        $this->cleanupResources();
    }

    /**
     * Handle execution failure.
     */
    protected function handleFailure(Throwable $exception, float $executionTime): void
    {
        // Log failure metrics
        $this->logExecutionMetrics('failure', $executionTime, null, $exception);

        // Update workflow statistics
        $this->updateWorkflowStatistics($executionTime, false);

        // Send alerts for critical failures
        if ($this->isCriticalFailure($exception)) {
            $this->sendCriticalFailureAlert($exception);
        }
    }

    /**
     * Log execution metrics.
     */
    protected function logExecutionMetrics(
        string $status,
        float $executionTime,
        $result = null,
        ?Throwable $exception = null
    ): void {
        $metrics = [
            'workflow_id' => $this->workflow->id,
            'execution_id' => $this->executionId,
            'status' => $status,
            'execution_time' => $executionTime,
            'queue' => $this->queue,
            'attempts' => $this->attempts(),
            'priority' => $this->priority,
            'timestamp' => now(),
        ];

        if ($result) {
            $metrics['result_size'] = strlen(json_encode($result->getOutputData()));
            $metrics['has_warnings'] = $result->hasWarnings();
        }

        if ($exception) {
            $metrics['error_type'] = get_class($exception);
            $metrics['error_message'] = $exception->getMessage();
        }

        Log::info('Workflow execution metrics', $metrics);

        // Store metrics for analytics
        $this->storeMetrics($metrics);
    }

    /**
     * Update workflow statistics.
     */
    protected function updateWorkflowStatistics(float $executionTime, bool $success): void
    {
        // Update workflow execution count
        $this->workflow->increment('execution_count');

        // Update last executed timestamp
        $this->workflow->update(['last_executed_at' => now()]);

        // Update success/failure rates (you might want to cache these)
        $stats = \Cache::get("workflow_stats_{$this->workflow->id}", [
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_execution_time' => 0,
        ]);

        $stats['total_executions']++;
        if ($success) {
            $stats['successful_executions']++;
        } else {
            $stats['failed_executions']++;
        }

        // Update average execution time
        $currentAvg = $stats['average_execution_time'];
        $stats['average_execution_time'] =
            (($currentAvg * ($stats['total_executions'] - 1)) + $executionTime) / $stats['total_executions'];

        \Cache::put("workflow_stats_{$this->workflow->id}", $stats, 86400); // Cache for 24 hours
    }

    /**
     * Store metrics for analytics.
     */
    protected function storeMetrics(array $metrics): void
    {
        $key = "execution_metrics:" . date('Y-m-d');
        $existingMetrics = \Cache::get($key, []);

        $existingMetrics[] = $metrics;

        // Keep only last 1000 metrics to prevent memory issues
        if (count($existingMetrics) > 1000) {
            $existingMetrics = array_slice($existingMetrics, -1000);
        }

        \Cache::put($key, $existingMetrics, 86400 * 7); // Keep for 7 days
    }

    /**
     * Trigger success callbacks.
     */
    protected function triggerSuccessCallbacks($result): void
    {
        // Implement webhook callbacks, email notifications, etc.
        // This could trigger other workflows or send notifications
    }

    /**
     * Send failure notification.
     */
    protected function sendFailureNotification(Throwable $exception): void
    {
        // Send email alerts to workflow owners or administrators
        // Log to external monitoring systems
    }

    /**
     * Check if failure is critical.
     */
    protected function isCriticalFailure(Throwable $exception): bool
    {
        // Define criteria for critical failures
        return $exception instanceof \Exception &&
               str_contains($exception->getMessage(), 'critical');
    }

    /**
     * Send critical failure alert.
     */
    protected function sendCriticalFailureAlert(Throwable $exception): void
    {
        // Send immediate alerts for critical failures
        Log::critical('Critical workflow execution failure', [
            'workflow_id' => $this->workflow->id,
            'execution_id' => $this->executionId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Clean up temporary resources.
     */
    protected function cleanupResources(): void
    {
        // Clean up temporary files, cache entries, etc.
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow:' . $this->workflow->id,
            'execution:' . $this->executionId,
            'organization:' . $this->workflow->organization_id,
            'priority:' . $this->priority,
        ];
    }
}
