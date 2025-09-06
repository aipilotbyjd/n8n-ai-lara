<?php

namespace App\Queue;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class QueueWorkerSupervisor
{
    protected array $workers = [];
    protected array $workerProcesses = [];
    protected int $maxWorkers = 10;
    protected int $minWorkers = 2;
    protected string $queueName;

    public function __construct(string $queueName = 'default')
    {
        $this->queueName = $queueName;
        $this->loadWorkerConfiguration();
    }

    /**
     * Start the supervisor
     */
    public function start(): void
    {
        Log::info('Starting queue worker supervisor', [
            'queue' => $this->queueName,
            'min_workers' => $this->minWorkers,
            'max_workers' => $this->maxWorkers,
        ]);

        $this->startMinimumWorkers();

        while (true) {
            $this->monitorAndScale();
            sleep(30); // Check every 30 seconds
        }
    }

    /**
     * Stop all workers
     */
    public function stop(): void
    {
        Log::info('Stopping queue worker supervisor', ['queue' => $this->queueName]);

        foreach ($this->workerProcesses as $process) {
            if ($process->isRunning()) {
                $process->stop(10); // Graceful shutdown with 10 second timeout
            }
        }

        $this->workerProcesses = [];
        $this->workers = [];
    }

    /**
     * Get current worker status
     */
    public function getStatus(): array
    {
        $status = [
            'total_workers' => count($this->workers),
            'active_workers' => 0,
            'idle_workers' => 0,
            'workers' => [],
        ];

        foreach ($this->workers as $workerId => $worker) {
            $isActive = isset($this->workerProcesses[$workerId]) &&
                       $this->workerProcesses[$workerId]->isRunning();

            $workerStatus = [
                'id' => $workerId,
                'status' => $isActive ? 'running' : 'stopped',
                'started_at' => $worker['started_at'] ?? null,
                'jobs_processed' => $worker['jobs_processed'] ?? 0,
                'last_activity' => $worker['last_activity'] ?? null,
            ];

            if ($isActive) {
                $status['active_workers']++;
            } else {
                $status['idle_workers']++;
            }

            $status['workers'][] = $workerStatus;
        }

        return $status;
    }

    /**
     * Scale workers based on queue load
     */
    public function scale(int $targetWorkers): void
    {
        $currentWorkers = count($this->workers);

        Log::info('Scaling workers', [
            'queue' => $this->queueName,
            'current' => $currentWorkers,
            'target' => $targetWorkers,
        ]);

        if ($targetWorkers > $currentWorkers) {
            $this->scaleUp($targetWorkers - $currentWorkers);
        } elseif ($targetWorkers < $currentWorkers) {
            $this->scaleDown($currentWorkers - $targetWorkers);
        }
    }

    /**
     * Monitor queue load and auto-scale
     */
    protected function monitorAndScale(): void
    {
        $queueStatus = $this->getQueueLoad();
        $currentWorkers = count($this->workers);
        $targetWorkers = $this->calculateTargetWorkers($queueStatus);

        // Apply bounds
        $targetWorkers = max($this->minWorkers, min($this->maxWorkers, $targetWorkers));

        if ($targetWorkers !== $currentWorkers) {
            $this->scale($targetWorkers);
        }

        // Update metrics
        $this->updateMetrics($queueStatus, $currentWorkers);
    }

    /**
     * Get current queue load
     */
    protected function getQueueLoad(): array
    {
        // Get queue statistics from Redis/cache
        return Cache::get("queue_load_{$this->queueName}", [
            'pending_jobs' => 0,
            'processing_jobs' => 0,
            'failed_jobs' => 0,
            'average_processing_time' => 0,
        ]);
    }

    /**
     * Calculate target number of workers
     */
    protected function calculateTargetWorkers(array $queueStatus): int
    {
        $pendingJobs = $queueStatus['pending_jobs'];
        $processingJobs = $queueStatus['processing_jobs'];

        // Simple scaling algorithm
        $targetWorkers = $this->minWorkers;

        // Scale up based on pending jobs
        if ($pendingJobs > 100) {
            $targetWorkers = min($this->maxWorkers, $this->minWorkers + ceil($pendingJobs / 50));
        } elseif ($pendingJobs > 50) {
            $targetWorkers = min($this->maxWorkers, $this->minWorkers + ceil($pendingJobs / 25));
        } elseif ($pendingJobs > 10) {
            $targetWorkers = min($this->maxWorkers, $this->minWorkers + ceil($pendingJobs / 10));
        }

        // Scale down if low activity
        if ($pendingJobs === 0 && $processingJobs === 0) {
            $targetWorkers = max($this->minWorkers, $currentWorkers ?? $this->minWorkers - 1);
        }

        return $targetWorkers;
    }

    /**
     * Start minimum number of workers
     */
    protected function startMinimumWorkers(): void
    {
        for ($i = 0; $i < $this->minWorkers; $i++) {
            $this->startWorker();
        }
    }

    /**
     * Start a new worker
     */
    protected function startWorker(): void
    {
        $workerId = uniqid('worker_');

        $process = new SymfonyProcess([
            'php',
            'artisan',
            'queue:work',
            $this->queueName,
            '--tries=3',
            '--timeout=3600',
            '--sleep=3',
            '--max-jobs=1000'
        ]);

        $process->setWorkingDirectory(base_path());
        $process->start();

        $this->workerProcesses[$workerId] = $process;
        $this->workers[$workerId] = [
            'started_at' => now(),
            'jobs_processed' => 0,
            'last_activity' => now(),
        ];

        Log::info('Started worker', [
            'worker_id' => $workerId,
            'queue' => $this->queueName,
            'pid' => $process->getPid(),
        ]);
    }

    /**
     * Scale up by starting new workers
     */
    protected function scaleUp(int $count): void
    {
        Log::info('Scaling up workers', [
            'queue' => $this->queueName,
            'count' => $count,
        ]);

        for ($i = 0; $i < $count; $i++) {
            $this->startWorker();
        }
    }

    /**
     * Scale down by stopping workers
     */
    protected function scaleDown(int $count): void
    {
        Log::info('Scaling down workers', [
            'queue' => $this->queueName,
            'count' => $count,
        ]);

        $workersToStop = array_slice(array_keys($this->workers), 0, $count);

        foreach ($workersToStop as $workerId) {
            $this->stopWorker($workerId);
        }
    }

    /**
     * Stop a specific worker
     */
    protected function stopWorker(string $workerId): void
    {
        if (isset($this->workerProcesses[$workerId])) {
            $process = $this->workerProcesses[$workerId];

            if ($process->isRunning()) {
                $process->stop(30); // 30 second graceful shutdown
                Log::info('Stopped worker', [
                    'worker_id' => $workerId,
                    'queue' => $this->queueName,
                ]);
            }

            unset($this->workerProcesses[$workerId]);
            unset($this->workers[$workerId]);
        }
    }

    /**
     * Update performance metrics
     */
    protected function updateMetrics(array $queueStatus, int $currentWorkers): void
    {
        $metrics = [
            'queue' => $this->queueName,
            'workers' => $currentWorkers,
            'pending_jobs' => $queueStatus['pending_jobs'],
            'processing_jobs' => $queueStatus['processing_jobs'],
            'failed_jobs' => $queueStatus['failed_jobs'],
            'timestamp' => now(),
        ];

        Cache::put("supervisor_metrics_{$this->queueName}", $metrics, 3600);
    }

    /**
     * Load worker configuration
     */
    protected function loadWorkerConfiguration(): void
    {
        $this->maxWorkers = config("queue.supervisor.{$this->queueName}.max_workers", 10);
        $this->minWorkers = config("queue.supervisor.{$this->queueName}.min_workers", 2);
    }

    /**
     * Get supervisor metrics
     */
    public function getMetrics(): array
    {
        return Cache::get("supervisor_metrics_{$this->queueName}", []);
    }

    /**
     * Health check for supervisor
     */
    public function healthCheck(): array
    {
        $status = $this->getStatus();
        $metrics = $this->getMetrics();

        return [
            'healthy' => $status['active_workers'] >= $this->minWorkers,
            'workers' => $status,
            'metrics' => $metrics,
            'last_check' => now(),
        ];
    }
}
