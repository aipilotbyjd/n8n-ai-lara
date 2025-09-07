<?php

namespace App\Http\Controllers;

use App\Http\Middleware\PerformanceMonitor;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceDashboardController extends Controller
{
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get performance dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->get('period', '1h');

        $dashboard = [
            'api_performance' => $this->getApiPerformanceStats($period),
            'cache_performance' => $this->getCachePerformanceStats(),
            'system_performance' => $this->getSystemPerformanceStats(),
            'recommendations' => $this->getPerformanceRecommendations(),
        ];

        return response()->json([
            'success' => true,
            'data' => $dashboard,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get API performance statistics
     */
    private function getApiPerformanceStats(string $period): array
    {
        $stats = PerformanceMonitor::getStats($period);

        return [
            'total_requests' => $stats['requests'] ?? 0,
            'average_response_time' => round($stats['avg_execution_time'] ?? 0, 2),
            'max_response_time' => round($stats['max_execution_time'] ?? 0, 2),
            'slow_requests_count' => $stats['slow_requests'] ?? 0,
            'error_rate' => $this->calculateErrorRate($stats),
        ];
    }

    /**
     * Get cache performance statistics
     */
    private function getCachePerformanceStats(): array
    {
        $cacheStats = $this->cacheService->getCacheStats();

        return [
            'redis_stats' => $cacheStats['redis'] ?? [],
            'file_cache_size' => $cacheStats['file_cache_size'] ?? '0B',
            'cache_hit_rate' => $cacheStats['cache_hit_rate'] ?? 0,
        ];
    }

    /**
     * Get system performance statistics
     */
    private function getSystemPerformanceStats(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
        ];
    }

    /**
     * Get performance recommendations
     */
    private function getPerformanceRecommendations(): array
    {
        $recommendations = [];

        $apiStats = PerformanceMonitor::getStats('1h');
        if (($apiStats['avg_execution_time'] ?? 0) > 500) {
            $recommendations[] = [
                'type' => 'api',
                'priority' => 'high',
                'title' => 'High API Response Time',
                'description' => 'Average API response time is above 500ms',
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate API error rate
     */
    private function calculateErrorRate(array $stats): float
    {
        $totalRequests = $stats['requests'] ?? 0;
        $errorRequests = 0;

        $statusCodes = $stats['status_codes'] ?? [];
        foreach ($statusCodes as $code => $count) {
            if ($code >= 400) {
                $errorRequests += $count;
            }
        }

        return $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;
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
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): array
    {
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsed = $diskTotal - $diskFree;

        return [
            'total' => $this->formatBytes($diskTotal),
            'used' => $this->formatBytes($diskUsed),
            'usage_percentage' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0,
        ];
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
}
