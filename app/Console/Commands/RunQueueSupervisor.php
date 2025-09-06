<?php

namespace App\Console\Commands;

use App\Queue\QueueWorkerSupervisor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunQueueSupervisor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:supervisor
                          {queue=default : The queue to supervise}
                          {--stop : Stop the supervisor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run queue worker supervisor with auto-scaling';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->argument('queue');
        $stop = $this->option('stop');

        if ($stop) {
            return $this->stopSupervisor($queue);
        }

        return $this->startSupervisor($queue);
    }

    /**
     * Start the queue supervisor
     */
    protected function startSupervisor(string $queue): int
    {
        $this->info("Starting queue supervisor for: {$queue}");

        try {
            $supervisor = new QueueWorkerSupervisor($queue);

            // Handle graceful shutdown
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use ($supervisor) {
                $this->info('Received shutdown signal, stopping supervisor...');
                $supervisor->stop();
                exit(0);
            });

            pcntl_signal(SIGINT, function () use ($supervisor) {
                $this->info('Received interrupt signal, stopping supervisor...');
                $supervisor->stop();
                exit(0);
            });

            $this->info('Queue supervisor started. Press Ctrl+C to stop.');
            $this->newLine();

            $supervisor->start();

        } catch (\Exception $e) {
            $this->error("Failed to start supervisor: {$e->getMessage()}");
            Log::error('Queue supervisor startup failed', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Stop the queue supervisor
     */
    protected function stopSupervisor(string $queue): int
    {
        $this->info("Stopping queue supervisor for: {$queue}");

        try {
            $supervisor = new QueueWorkerSupervisor($queue);
            $supervisor->stop();

            $this->info('Queue supervisor stopped successfully.');
            Log::info('Queue supervisor stopped', ['queue' => $queue]);

        } catch (\Exception $e) {
            $this->error("Failed to stop supervisor: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
