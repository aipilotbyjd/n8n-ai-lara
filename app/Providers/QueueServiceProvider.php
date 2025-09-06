<?php

namespace App\Providers;

use App\Queue\QueueManager;
use App\Queue\QueueWorkerSupervisor;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;

class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Queue Manager
        $this->app->singleton(QueueManager::class, function ($app) {
            return new QueueManager();
        });

        // Register Queue Worker Supervisor
        $this->app->bind(QueueWorkerSupervisor::class, function ($app) {
            return new QueueWorkerSupervisor();
        });

        // Register queue-specific supervisors
        $this->app->when(QueueWorkerSupervisor::class)
            ->needs('$queueName')
            ->give(function () {
                return 'default'; // Default queue name
            });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register queue event listeners
        Queue::before(function ($event) {
            // Log job started
            \Log::info('Job started', [
                'job' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'id' => $event->job->getJobId(),
            ]);
        });

        Queue::after(function ($event) {
            // Log job completed
            \Log::info('Job completed', [
                'job' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'id' => $event->job->getJobId(),
                'execution_time' => $event->job->getExecutionTime(),
            ]);
        });

        Queue::failing(function ($event) {
            // Log job failed
            \Log::error('Job failed', [
                'job' => $event->job->getName(),
                'queue' => $event->job->getQueue(),
                'id' => $event->job->getJobId(),
                'exception' => $event->exception->getMessage(),
                'attempts' => $event->job->attempts(),
            ]);
        });

        Queue::looping(function ($event) {
            // Clean up old cached data periodically
            $queueManager = app(QueueManager::class);
            $queueManager->cleanup();
        });
    }
}
