<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    private const CACHE_KEY = 'performance_metrics';
    private const SLOW_QUERY_THRESHOLD = 1000; // milliseconds
    private const MEMORY_THRESHOLD = 50 * 1024 * 1024; // 50MB

    /**
     * Handle an incoming request with performance monitoring.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startCpu = $this->getCpuUsage();

        // Track request
        $this->trackRequest($request);

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endCpu = $this->getCpuUsage();

        // Calculate metrics
        $executionTime = ($endTime - $startTime) * 1000; // milliseconds
        $memoryUsage = $endMemory - $startMemory;
        $cpuUsage = $endCpu - $startCpu;

        // Store metrics
        $this->storeMetrics($request, $response, $executionTime, $memoryUsage, $cpuUsage);

        // Log slow requests
        if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            $this->logSlowRequest($request, $executionTime, $memoryUsage);
        }

        // Add performance headers to response
        $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsage));
        $response->headers->set('X-Cache-Status', $this->getCacheStatus($request));

        return $response;
    }

    /**
     * Track incoming request
     */
    private function trackRequest(Request $request): void
    {
        $key = self::CACHE_KEY . ':requests:' . date('Y-m-d-H');

        $current = Cache::get($key, [
            'total' => 0,
            'by_endpoint' => [],
            'by_method' => [],
        ]);

        $endpoint = $request->path();
        $method = $request->method();

        $current['total']++;
        $current['by_endpoint'][$endpoint] = ($current['by_endpoint'][$endpoint] ?? 0) + 1;
        $current['by_method'][$method] = ($current['by_method'][$method] ?? 0) + 1;

        Cache::put($key, $current, 3600); // 1 hour
    }

    /**
     * Store performance metrics
     */
    private function storeMetrics(Request $request, Response $response, float $executionTime, int $memoryUsage, float $cpuUsage): void
    {
        $metricsKey = self::CACHE_KEY . ':metrics:' . date('Y-m-d-H-i');

        $metrics = Cache::get($metricsKey, [
            'requests' => 0,
            'total_execution_time' => 0,
            'avg_execution_time' => 0,
            'max_execution_time' => 0,
            'total_memory_usage' => 0,
            'avg_memory_usage' => 0,
            'max_memory_usage' => 0,
            'status_codes' => [],
            'slow_requests' => 0,
        ]);

        $metrics['requests']++;
        $metrics['total_execution_time'] += $executionTime;
        $metrics['avg_execution_time'] = $metrics['total_execution_time'] / $metrics['requests'];
        $metrics['max_execution_time'] = max($metrics['max_execution_time'], $executionTime);

        $metrics['total_memory_usage'] += $memoryUsage;
        $metrics['avg_memory_usage'] = $metrics['total_memory_usage'] / $metrics['requests'];
        $metrics['max_memory_usage'] = max($metrics['max_memory_usage'], $memoryUsage);

        $statusCode = $response->getStatusCode();
        $metrics['status_codes'][$statusCode] = ($metrics['status_codes'][$statusCode] ?? 0) + 1;

        if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            $metrics['slow_requests']++;
        }

        Cache::put($metricsKey, $metrics, 3600); // 1 hour
    }

    /**
     * Log slow requests
     */
    private function logSlowRequest(Request $request, float $executionTime, int $memoryUsage): void
    {
        Log::warning('Slow API request detected', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'execution_time' => round($executionTime, 2) . 'ms',
            'memory_usage' => $this->formatBytes($memoryUsage),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get CPU usage
     */
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? 0;
        }

        return 0;
    }

    /**
     * Get cache status
     */
    private function getCacheStatus(Request $request): string
    {
        // Check if response was served from cache
        $cacheHeaders = ['X-Cache', 'CF-Cache-Status', 'X-Cache-Status'];

        foreach ($cacheHeaders as $header) {
            if ($request->headers->has($header)) {
                return $request->headers->get($header);
            }
        }

        return 'MISS';
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . $units[$i];
    }

    /**
     * Get performance statistics
     */
    public static function getStats(string $period = '1h'): array
    {
        $now = now();
        $stats = [];

        switch ($period) {
            case '1h':
                $keys = [self::CACHE_KEY . ':metrics:' . $now->format('Y-m-d-H-i')];
                break;
            case '24h':
                $keys = [];
                for ($i = 0; $i < 24; $i++) {
                    $time = $now->copy()->subHours($i);
                    $keys[] = self::CACHE_KEY . ':metrics:' . $time->format('Y-m-d-H');
                }
                break;
            default:
                return [];
        }

        foreach ($keys as $key) {
            $metrics = Cache::get($key, []);
            if (!empty($metrics)) {
                $stats[] = $metrics;
            }
        }

        return self::aggregateStats($stats);
    }

    /**
     * Aggregate statistics from multiple time periods
     */
    private static function aggregateStats(array $stats): array
    {
        if (empty($stats)) {
            return [
                'requests' => 0,
                'avg_execution_time' => 0,
                'max_execution_time' => 0,
                'avg_memory_usage' => 0,
                'slow_requests' => 0,
                'status_codes' => [],
            ];
        }

        $totalRequests = array_sum(array_column($stats, 'requests'));
        $totalExecutionTime = array_sum(array_column($stats, 'total_execution_time'));
        $totalMemoryUsage = array_sum(array_column($stats, 'total_memory_usage'));

        $aggregated = [
            'requests' => $totalRequests,
            'avg_execution_time' => $totalRequests > 0 ? $totalExecutionTime / $totalRequests : 0,
            'max_execution_time' => max(array_column($stats, 'max_execution_time')),
            'avg_memory_usage' => $totalRequests > 0 ? $totalMemoryUsage / $totalRequests : 0,
            'slow_requests' => array_sum(array_column($stats, 'slow_requests')),
            'status_codes' => [],
        ];

        // Aggregate status codes
        foreach ($stats as $stat) {
            foreach ($stat['status_codes'] as $code => $count) {
                $aggregated['status_codes'][$code] = ($aggregated['status_codes'][$code] ?? 0) + $count;
            }
        }

        return $aggregated;
    }

    /**
     * Clear performance metrics
     */
    public static function clearMetrics(): void
    {
        $pattern = self::CACHE_KEY . '*';
        Cache::flush();
    }
}
