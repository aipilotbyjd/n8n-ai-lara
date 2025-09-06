<?php

namespace App\Queue;

use App\Jobs\ProcessWorkflowExecution;
use App\Models\Workflow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class QueueManager
{
    /**
     * Dispatch workflow execution to queue
     */
    public function dispatchWorkflowExecution(
        Workflow $workflow,
        array $triggerData = [],
        string $priority = 'normal',
        int $delay = 0
    ): string {
        Log::info('Dispatching workflow execution to queue', [
            'workflow_id' => $workflow->id,
            'priority' => $priority,
            'delay' => $delay,
        ]);

        $job = new ProcessWorkflowExecution($workflow, $triggerData, null, $priority);

        if ($delay > 0) {
            $jobId = Queue::later($delay, $job);
        } else {
            $jobId = Queue::push($job);
        }

        // Cache job information for tracking
        $this->cacheJobInfo($jobId, $workflow->id, $priority);

        Log::info('Workflow execution dispatched', [
            'job_id' => $jobId,
            'workflow_id' => $workflow->id,
        ]);

        return $jobId;
    }

    /**
     * Dispatch workflow execution with existing execution record
     */
    public function dispatchWorkflowExecutionWithRecord(
        Workflow $workflow,
        int $executionId,
        array $triggerData = [],
        string $priority = 'normal'
    ): string {
        Log::info('Dispatching workflow execution with record', [
            'workflow_id' => $workflow->id,
            'execution_id' => $executionId,
            'priority' => $priority,
        ]);

        $job = new ProcessWorkflowExecution($workflow, $triggerData, $executionId, $priority);
        $jobId = Queue::push($job);

        $this->cacheJobInfo($jobId, $workflow->id, $priority, $executionId);

        return $jobId;
    }

    /**
     * Get queue status and statistics
     */
    public function getQueueStatus(): array
    {
        $queues = ['default', 'high-priority', 'low-priority'];

        $status = [];
        foreach ($queues as $queue) {
            $status[$queue] = [
                'pending' => Queue::size($queue),
                'failed' => $this->getFailedJobsCount($queue),
                'processing' => $this->getProcessingJobsCount($queue),
            ];
        }

        $status['total'] = [
            'pending' => array_sum(array_column($status, 'pending')),
            'failed' => array_sum(array_column($status, 'failed')),
            'processing' => array_sum(array_column($status, 'processing')),
        ];

        return $status;
    }

    /**
     * Get pending jobs for a specific queue
     */
    public function getPendingJobs(string $queue = null, int $limit = 50): array
    {
        // This would require Redis or database inspection
        // For now, return cached job information
        return Cache::get("queue_pending_{$queue}", []);
    }

    /**
     * Get failed jobs count for a queue
     */
    public function getFailedJobsCount(string $queue = null): int
    {
        // This would integrate with Laravel's failed jobs table
        return Cache::get("queue_failed_{$queue}", 0);
    }

    /**
     * Get processing jobs count for a queue
     */
    public function getProcessingJobsCount(string $queue = null): int
    {
        return Cache::get("queue_processing_{$queue}", 0);
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(string $queue = null, int $limit = 10): int
    {
        // This would integrate with Laravel's failed jobs system
        Log::info('Retrying failed jobs', ['queue' => $queue, 'limit' => $limit]);
        return 0; // Placeholder
    }

    /**
     * Clear failed jobs
     */
    public function clearFailedJobs(string $queue = null): int
    {
        Log::info('Clearing failed jobs', ['queue' => $queue]);
        return 0; // Placeholder
    }

    /**
     * Get job information by ID
     */
    public function getJobInfo(string $jobId): ?array
    {
        return Cache::get("job_{$jobId}");
    }

    /**
     * Cancel a queued job
     */
    public function cancelJob(string $jobId): bool
    {
        $jobInfo = $this->getJobInfo($jobId);
        if (!$jobInfo) {
            return false;
        }

        // This would require Redis queue inspection and removal
        Log::info('Cancelling job', ['job_id' => $jobId]);
        Cache::forget("job_{$jobId}");

        return true;
    }

    /**
     * Get queue performance metrics
     */
    public function getQueueMetrics(): array
    {
        return [
            'throughput' => $this->getThroughputMetrics(),
            'latency' => $this->getLatencyMetrics(),
            'error_rate' => $this->getErrorRateMetrics(),
            'resource_usage' => $this->getResourceUsageMetrics(),
        ];
    }

    /**
     * Get throughput metrics (jobs processed per minute/hour)
     */
    private function getThroughputMetrics(): array
    {
        $hourly = Cache::get('queue_throughput_hourly', []);
        $daily = Cache::get('queue_throughput_daily', []);

        return [
            'hourly' => $hourly,
            'daily' => $daily,
            'average_hourly' => count($hourly) > 0 ? array_sum($hourly) / count($hourly) : 0,
            'average_daily' => count($daily) > 0 ? array_sum($daily) / count($daily) : 0,
        ];
    }

    /**
     * Get latency metrics (time from dispatch to completion)
     */
    private function getLatencyMetrics(): array
    {
        return Cache::get('queue_latency_metrics', [
            'average' => 0,
            'p50' => 0,
            'p95' => 0,
            'p99' => 0,
        ]);
    }

    /**
     * Get error rate metrics
     */
    private function getErrorRateMetrics(): array
    {
        return Cache::get('queue_error_metrics', [
            'total_errors' => 0,
            'error_rate_percentage' => 0,
            'most_common_errors' => [],
        ]);
    }

    /**
     * Get resource usage metrics
     */
    private function getResourceUsageMetrics(): array
    {
        return Cache::get('queue_resource_metrics', [
            'cpu_usage' => 0,
            'memory_usage' => 0,
            'disk_usage' => 0,
            'network_usage' => 0,
        ]);
    }

    /**
     * Cache job information for tracking
     */
    private function cacheJobInfo(string $jobId, int $workflowId, string $priority, ?int $executionId = null): void
    {
        $jobInfo = [
            'job_id' => $jobId,
            'workflow_id' => $workflowId,
            'execution_id' => $executionId,
            'priority' => $priority,
            'status' => 'queued',
            'queued_at' => now(),
        ];

        Cache::put("job_{$jobId}", $jobInfo, 86400); // Cache for 24 hours

        // Add to queue tracking
        $queueKey = "queue_pending_{$this->getQueueName($priority)}";
        $pendingJobs = Cache::get($queueKey, []);
        $pendingJobs[] = $jobId;
        Cache::put($queueKey, $pendingJobs, 3600); // Cache for 1 hour
    }

    /**
     * Get queue name from priority
     */
    private function getQueueName(string $priority): string
    {
        return match ($priority) {
            'high' => 'high-priority',
            'low' => 'low-priority',
            default => 'default',
        };
    }

    /**
     * Clean up old cached data
     */
    public function cleanup(): void
    {
        // Clean up old job information
        $oldJobKeys = Cache::get('old_job_keys', []);
        foreach ($oldJobKeys as $key) {
            Cache::forget($key);
        }

        // Reset cleanup tracking
        Cache::put('old_job_keys', [], 86400);
    }

    /**
     * Monitor queue health
     */
    public function getHealthStatus(): array
    {
        $status = $this->getQueueStatus();

        return [
            'overall_health' => $this->calculateHealthScore($status),
            'queues' => $status,
            'recommendations' => $this->getHealthRecommendations($status),
            'last_checked' => now(),
        ];
    }

    /**
     * Calculate overall health score (0-100)
     */
    private function calculateHealthScore(array $status): int
    {
        $totalPending = $status['total']['pending'];
        $totalFailed = $status['total']['failed'];
        $totalProcessing = $status['total']['processing'];

        // Health score calculation
        $healthScore = 100;

        // Deduct points for pending jobs
        if ($totalPending > 100) {
            $healthScore -= 30;
        } elseif ($totalPending > 50) {
            $healthScore -= 15;
        } elseif ($totalPending > 10) {
            $healthScore -= 5;
        }

        // Deduct points for failed jobs
        if ($totalFailed > 10) {
            $healthScore -= 40;
        } elseif ($totalFailed > 5) {
            $healthScore -= 20;
        } elseif ($totalFailed > 0) {
            $healthScore -= 10;
        }

        return max(0, min(100, $healthScore));
    }

    /**
     * Get health recommendations
     */
    private function getHealthRecommendations(array $status): array
    {
        $recommendations = [];

        if ($status['total']['pending'] > 100) {
            $recommendations[] = 'High queue backlog detected. Consider scaling up workers.';
        }

        if ($status['total']['failed'] > 10) {
            $recommendations[] = 'High failure rate detected. Check error logs and retry failed jobs.';
        }

        if ($status['total']['pending'] === 0 && $status['total']['processing'] === 0) {
            $recommendations[] = 'Queue is idle. Consider scaling down workers to save resources.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Queue health is good. All systems operational.';
        }

        return $recommendations;
    }
}
