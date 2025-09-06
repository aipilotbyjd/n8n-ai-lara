<?php

namespace App\Console\Commands;

use App\Queue\QueueManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:status
                          {--watch : Watch queue status in real-time}
                          {--json : Output in JSON format}
                          {--queue= : Specific queue to monitor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display queue status and performance metrics';

    /**
     * Execute the console command.
     */
    public function handle(QueueManager $queueManager)
    {
        $watch = $this->option('watch');
        $json = $this->option('json');
        $specificQueue = $this->option('queue');

        if ($watch) {
            return $this->watchMode($queueManager, $json, $specificQueue);
        }

        return $this->singleReport($queueManager, $json, $specificQueue);
    }

    /**
     * Display single queue status report
     */
    protected function singleReport(QueueManager $queueManager, bool $json, ?string $specificQueue): int
    {
        $status = $queueManager->getQueueStatus();
        $health = $queueManager->getHealthStatus();
        $metrics = $queueManager->getQueueMetrics();

        if ($specificQueue) {
            $status = [$specificQueue => $status[$specificQueue] ?? ['pending' => 0, 'failed' => 0, 'processing' => 0]];
        }

        if ($json) {
            $this->output->writeln(json_encode([
                'status' => $status,
                'health' => $health,
                'metrics' => $metrics,
                'timestamp' => now(),
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        $this->displayHeader();
        $this->displayQueueStatus($status);
        $this->displayHealthStatus($health);
        $this->displayMetrics($metrics);

        return 0;
    }

    /**
     * Watch mode for real-time monitoring
     */
    protected function watchMode(QueueManager $queueManager, bool $json, ?string $specificQueue): int
    {
        $this->info('Watching queue status... Press Ctrl+C to stop');

        while (true) {
            if ($json) {
                $status = $queueManager->getQueueStatus();
                $health = $queueManager->getHealthStatus();
                $metrics = $queueManager->getQueueMetrics();

                if ($specificQueue) {
                    $status = [$specificQueue => $status[$specificQueue] ?? ['pending' => 0, 'failed' => 0, 'processing' => 0]];
                }

                $this->output->write("\033[2J\033[H"); // Clear screen
                $this->output->writeln(json_encode([
                    'status' => $status,
                    'health' => $health,
                    'metrics' => $metrics,
                    'timestamp' => now(),
                ], JSON_PRETTY_PRINT));
            } else {
                $this->output->write("\033[2J\033[H"); // Clear screen
                $status = $queueManager->getQueueStatus();
                $health = $queueManager->getHealthStatus();
                $metrics = $queueManager->getQueueMetrics();

                if ($specificQueue) {
                    $status = [$specificQueue => $status[$specificQueue] ?? ['pending' => 0, 'failed' => 0, 'processing' => 0]];
                }

                $this->displayHeader();
                $this->displayQueueStatus($status);
                $this->displayHealthStatus($health);
                $this->displayMetrics($metrics);
            }

            sleep(5); // Update every 5 seconds
        }

        return 0;
    }

    /**
     * Display header
     */
    protected function displayHeader(): void
    {
        $this->info('🚀 N8N Queue Status Monitor');
        $this->line('═'.str_repeat('═', 60).'═');
        $this->line('Last updated: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();
    }

    /**
     * Display queue status
     */
    protected function displayQueueStatus(array $status): void
    {
        $this->info('📊 Queue Status:');
        $this->newLine();

        $headers = ['Queue', 'Pending', 'Processing', 'Failed', 'Total'];
        $rows = [];

        foreach ($status as $queue => $stats) {
            if ($queue === 'total') continue;

            $rows[] = [
                $queue,
                number_format($stats['pending']),
                number_format($stats['processing']),
                number_format($stats['failed']),
                number_format($stats['pending'] + $stats['processing'] + $stats['failed']),
            ];
        }

        // Add totals
        if (isset($status['total'])) {
            $total = $status['total'];
            $rows[] = [
                'TOTAL',
                number_format($total['pending']),
                number_format($total['processing']),
                number_format($total['failed']),
                number_format($total['pending'] + $total['processing'] + $total['failed']),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Display health status
     */
    protected function displayHealthStatus(array $health): void
    {
        $this->info('🏥 Health Status:');
        $this->newLine();

        $healthScore = $health['overall_health'];
        $healthColor = $this->getHealthColor($healthScore);

        $this->line("Overall Health: <{$healthColor}>{$healthScore}/100</{$healthColor}>");

        $this->newLine();
        $this->info('Recommendations:');
        foreach ($health['recommendations'] as $recommendation) {
            $this->line("• {$recommendation}");
        }

        $this->newLine();
    }

    /**
     * Display performance metrics
     */
    protected function displayMetrics(array $metrics): void
    {
        $this->info('📈 Performance Metrics:');
        $this->newLine();

        // Throughput
        if (!empty($metrics['throughput']['hourly'])) {
            $this->line('Throughput (jobs/hour):');
            $hourly = end($metrics['throughput']['hourly']);
            $this->line("  Current: {$hourly} jobs/hour");

            if (count($metrics['throughput']['hourly']) > 1) {
                $previous = prev($metrics['throughput']['hourly']);
                $change = $hourly - $previous;
                $changeColor = $change >= 0 ? 'green' : 'red';
                $changeSymbol = $change >= 0 ? '+' : '';
                $this->line("  Change: <{$changeColor}>{$changeSymbol}{$change}</{$changeColor}> jobs/hour");
            }
        }

        // Latency
        if (!empty($metrics['latency'])) {
            $this->newLine();
            $this->line('Latency (milliseconds):');
            $this->line("  P50: {$metrics['latency']['p50']}ms");
            $this->line("  P95: {$metrics['latency']['p95']}ms");
            $this->line("  P99: {$metrics['latency']['p99']}ms");
        }

        // Error Rate
        if (!empty($metrics['error_rate'])) {
            $this->newLine();
            $this->line('Error Rate:');
            $this->line("  Rate: {$metrics['error_rate']['error_rate_percentage']}%");

            if (!empty($metrics['error_rate']['most_common_errors'])) {
                $this->line('  Most Common Errors:');
                foreach (array_slice($metrics['error_rate']['most_common_errors'], 0, 3) as $error) {
                    $this->line("    • {$error['type']}: {$error['count']} times");
                }
            }
        }

        $this->newLine();
    }

    /**
     * Get health color for display
     */
    protected function getHealthColor(int $score): string
    {
        if ($score >= 80) {
            return 'green';
        } elseif ($score >= 60) {
            return 'yellow';
        } else {
            return 'red';
        }
    }
}
