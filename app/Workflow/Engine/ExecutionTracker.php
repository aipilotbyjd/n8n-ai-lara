<?php

namespace App\Workflow\Engine;

use App\Models\Execution;
use App\Models\Workflow;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExecutionTracker
{
    /**
     * Track node execution
     */
    public function trackNodeExecution(
        Execution $execution,
        string $nodeId,
        NodeExecutionResult $result,
        float $executionTime
    ): void {
        $nodeExecutionData = [
            'node_id' => $nodeId,
            'execution_time' => $executionTime,
            'success' => $result->isSuccess(),
            'error_message' => $result->getErrorMessage(),
            'data_size' => $result->getDataSize(),
            'timestamp' => now(),
        ];

        // Store in execution metadata
        $metadata = $execution->metadata ?? [];
        $metadata['node_executions'] = $metadata['node_executions'] ?? [];
        $metadata['node_executions'][] = $nodeExecutionData;

        $execution->update(['metadata' => $metadata]);

        // Update workflow statistics
        $this->updateWorkflowStats($execution->workflow, $nodeExecutionData);

        // Log execution for monitoring
        $this->logExecution($execution, $nodeExecutionData);
    }

    /**
     * Update workflow execution statistics
     */
    private function updateWorkflowStats(Workflow $workflow, array $nodeExecutionData): void
    {
        $stats = Cache::get("workflow_stats_{$workflow->id}", [
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_execution_time' => 0,
            'last_execution_at' => null,
        ]);

        $stats['total_executions']++;
        $stats['last_execution_at'] = now();

        if ($nodeExecutionData['success']) {
            $stats['successful_executions']++;
        } else {
            $stats['failed_executions']++;
        }

        // Update average execution time
        $currentAvg = $stats['average_execution_time'];
        $newExecutionTime = $nodeExecutionData['execution_time'];
        $stats['average_execution_time'] = (($currentAvg * ($stats['total_executions'] - 1)) + $newExecutionTime) / $stats['total_executions'];

        Cache::put("workflow_stats_{$workflow->id}", $stats, 86400); // Cache for 24 hours
    }

    /**
     * Log execution for monitoring and debugging
     */
    private function logExecution(Execution $execution, array $nodeExecutionData): void
    {
        $logData = [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'organization_id' => $execution->organization_id,
            'node_id' => $nodeExecutionData['node_id'],
            'execution_time' => $nodeExecutionData['execution_time'],
            'success' => $nodeExecutionData['success'],
            'data_size' => $nodeExecutionData['data_size'],
        ];

        if ($nodeExecutionData['success']) {
            Log::info('Node execution successful', $logData);
        } else {
            Log::warning('Node execution failed', array_merge($logData, [
                'error' => $nodeExecutionData['error_message'],
            ]));
        }
    }

    /**
     * Get workflow execution statistics
     */
    public function getWorkflowStats(Workflow $workflow): array
    {
        return Cache::get("workflow_stats_{$workflow->id}", [
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_execution_time' => 0,
            'last_execution_at' => null,
        ]);
    }

    /**
     * Get execution summary for dashboard
     */
    public function getExecutionSummary(int $organizationId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $executions = Execution::where('organization_id', $organizationId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $summary = [
            'total_executions' => $executions->count(),
            'successful_executions' => $executions->where('status', 'success')->count(),
            'failed_executions' => $executions->where('status', 'error')->count(),
            'running_executions' => $executions->where('status', 'running')->count(),
            'average_execution_time' => $executions->avg('duration') ?? 0,
            'total_execution_time' => $executions->sum('duration') ?? 0,
            'most_active_workflow' => $this->getMostActiveWorkflow($executions),
            'execution_trend' => $this->getExecutionTrend($executions, $days),
        ];

        return $summary;
    }

    /**
     * Get most active workflow
     */
    private function getMostActiveWorkflow($executions): ?array
    {
        $workflowCounts = $executions->groupBy('workflow_id')
            ->map(function ($group) {
                return $group->count();
            })
            ->sortDesc()
            ->first();

        if ($workflowCounts) {
            $workflowId = $workflowCounts->keys()->first();
            $count = $workflowCounts->first();

            return [
                'workflow_id' => $workflowId,
                'execution_count' => $count,
            ];
        }

        return null;
    }

    /**
     * Get execution trend over time
     */
    private function getExecutionTrend($executions, int $days): array
    {
        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $executions->filter(function ($execution) use ($date) {
                return $execution->created_at->format('Y-m-d') === $date;
            })->count();

            $trend[] = [
                'date' => $date,
                'executions' => $count,
            ];
        }

        return $trend;
    }

    /**
     * Track execution performance metrics
     */
    public function trackPerformanceMetrics(Execution $execution): void
    {
        $metrics = [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'organization_id' => $execution->organization_id,
            'duration' => $execution->duration,
            'status' => $execution->status,
            'node_count' => count($execution->metadata['node_executions'] ?? []),
            'data_size' => strlen(json_encode($execution->output_data ?? [])),
            'timestamp' => $execution->created_at,
        ];

        // Store metrics for analytics
        $this->storeMetrics($metrics);

        // Check for performance alerts
        $this->checkPerformanceAlerts($metrics);
    }

    /**
     * Store metrics for analytics
     */
    private function storeMetrics(array $metrics): void
    {
        $key = "execution_metrics:" . date('Y-m-d');
        $existingMetrics = Cache::get($key, []);

        $existingMetrics[] = $metrics;

        // Keep only last 1000 metrics to prevent memory issues
        if (count($existingMetrics) > 1000) {
            $existingMetrics = array_slice($existingMetrics, -1000);
        }

        Cache::put($key, $existingMetrics, 86400 * 7); // Keep for 7 days
    }

    /**
     * Check for performance alerts
     */
    private function checkPerformanceAlerts(array $metrics): void
    {
        // Alert if execution takes too long
        if ($metrics['duration'] > 300000) { // 5 minutes in milliseconds
            Log::warning('Slow execution detected', [
                'execution_id' => $metrics['execution_id'],
                'duration' => $metrics['duration'],
                'workflow_id' => $metrics['workflow_id'],
            ]);
        }

        // Alert if workflow fails
        if ($metrics['status'] === 'error') {
            Log::warning('Workflow execution failed', [
                'execution_id' => $metrics['execution_id'],
                'workflow_id' => $metrics['workflow_id'],
            ]);
        }
    }

    /**
     * Clean up old cached data
     */
    public function cleanup(): void
    {
        // Clean up old workflow stats
        $oldStatsKeys = Cache::store('redis')->keys('workflow_stats_*');
        foreach ($oldStatsKeys as $key) {
            $stats = Cache::get($key);
            if ($stats && isset($stats['last_execution_at'])) {
                $lastExecution = $stats['last_execution_at'];
                if ($lastExecution && $lastExecution->diffInDays(now()) > 30) {
                    Cache::forget($key);
                }
            }
        }

        // Clean up old execution metrics
        $oldMetricsKeys = Cache::store('redis')->keys('execution_metrics:*');
        foreach ($oldMetricsKeys as $key) {
            $date = str_replace('execution_metrics:', '', $key);
            if (strtotime($date) < strtotime('-30 days')) {
                Cache::forget($key);
            }
        }
    }
}
