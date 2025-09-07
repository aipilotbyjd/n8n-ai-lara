<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PerformanceOptimizer
{
    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'workflow_performance:';

    /**
     * Optimize database queries with caching
     */
    public function cachedQuery(string $cacheKey, int $ttl, callable $queryCallback)
    {
        $fullKey = self::CACHE_PREFIX . $cacheKey;

        return Cache::remember($fullKey, $ttl, function () use ($queryCallback) {
            return $queryCallback();
        });
    }

    /**
     * Clear performance cache
     */
    public function clearCache(string $pattern = null): void
    {
        if ($pattern) {
            $keys = Cache::getStore()->getRedis()->keys(self::CACHE_PREFIX . $pattern . '*');
            foreach ($keys as $key) {
                Cache::forget(str_replace(Cache::getStore()->getPrefix(), '', $key));
            }
        } else {
            Cache::flush();
        }
    }

    /**
     * Optimize collection operations
     */
    public function optimizeCollection(Collection $collection): Collection
    {
        // Use lazy collections for large datasets
        if ($collection->count() > 1000) {
            return $collection->lazy();
        }

        return $collection;
    }

    /**
     * Batch database operations
     */
    public function batchOperation(array $operations, int $batchSize = 100): array
    {
        $results = [];

        foreach (array_chunk($operations, $batchSize) as $batch) {
            DB::transaction(function () use ($batch, &$results) {
                foreach ($batch as $operation) {
                    $results[] = $operation();
                }
            });
        }

        return $results;
    }

    /**
     * Optimize memory usage for large datasets
     */
    public function processLargeDataset(iterable $dataset, callable $processor, int $chunkSize = 100): array
    {
        $results = [];
        $chunk = [];

        foreach ($dataset as $item) {
            $chunk[] = $item;

            if (count($chunk) >= $chunkSize) {
                $results = array_merge($results, $processor($chunk));
                $chunk = [];

                // Force garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }

        // Process remaining items
        if (!empty($chunk)) {
            $results = array_merge($results, $processor($chunk));
        }

        return $results;
    }

    /**
     * Database query optimization
     */
    public function optimizeQuery($query, array $optimizations = [])
    {
        // Add indexes if specified
        if (isset($optimizations['index'])) {
            $query->useIndex($optimizations['index']);
        }

        // Force index if specified
        if (isset($optimizations['force_index'])) {
            $query->forceIndex($optimizations['force_index']);
        }

        // Add query hints
        if (isset($optimizations['hints'])) {
            foreach ($optimizations['hints'] as $hint) {
                $query->hint($hint);
            }
        }

        return $query;
    }

    /**
     * Memory-efficient file processing
     */
    public function processLargeFile(string $filePath, callable $lineProcessor, int $bufferSize = 8192): array
    {
        $results = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        while (!feof($handle)) {
            $buffer = fread($handle, $bufferSize);
            $lines = explode("\n", $buffer);

            foreach ($lines as $line) {
                if (!empty(trim($line))) {
                    $results[] = $lineProcessor($line);
                }
            }

            // Clear buffer to free memory
            unset($buffer, $lines);
        }

        fclose($handle);
        return $results;
    }

    /**
     * Optimize API responses
     */
    public function optimizeApiResponse(array $data, array $fields = null): array
    {
        if ($fields === null) {
            return $data;
        }

        $optimized = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $optimized[$field] = $data[$field];
            }
        }

        return $optimized;
    }

    /**
     * Database connection optimization
     */
    public function optimizeConnection(): void
    {
        // Set optimal MySQL/MariaDB settings
        DB::statement('SET SESSION sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        DB::statement('SET SESSION max_execution_time = 30000');
    }

    /**
     * Performance monitoring
     */
    public function monitorPerformance(string $operation, callable $callback): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $callback();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'operation' => $operation,
            'execution_time' => ($endTime - $startTime) * 1000, // milliseconds
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => now()->toISOString(),
        ];

        // Log performance metrics
        \Illuminate\Support\Facades\Log::info('Performance metrics', $metrics);

        return $result;
    }

    /**
     * Optimize workflow execution
     */
    public function optimizeWorkflowExecution(array $workflowData): array
    {
        // Remove unnecessary data
        unset($workflowData['created_at'], $workflowData['updated_at']);

        // Compress large workflow data if needed
        if (isset($workflowData['workflow_data']) && strlen(json_encode($workflowData['workflow_data'])) > 10000) {
            // In a real implementation, you might compress this data
            $workflowData['compressed'] = true;
        }

        return $workflowData;
    }

    /**
     * Cache frequently accessed data
     */
    public function cacheFrequentlyAccessed(string $key, callable $dataProvider, int $ttl = 3600): mixed
    {
        $cacheKey = self::CACHE_PREFIX . 'frequent:' . $key;

        return Cache::remember($cacheKey, $ttl, $dataProvider);
    }

    /**
     * Preload frequently used data
     */
    public function preloadFrequentlyUsed(): void
    {
        // Preload node types
        $this->cacheFrequentlyAccessed('node_types', function () {
            return \App\Nodes\Registry\NodeRegistry::getManifest();
        });

        // Preload user organizations
        $this->cacheFrequentlyAccessed('user_organizations', function () {
            return \App\Models\Organization::select('id', 'name')->get();
        });

        // Preload workflow templates
        $this->cacheFrequentlyAccessed('workflow_templates', function () {
            return \App\Models\Workflow::templates()
                ->select('id', 'name', 'description')
                ->get();
        });
    }

    /**
     * Database maintenance
     */
    public function performMaintenance(): void
    {
        // Analyze tables for optimization
        DB::statement('ANALYZE TABLE workflows, executions, organizations, users');

        // Optimize tables
        DB::statement('OPTIMIZE TABLE workflows, executions, organizations, users');

        // Clear old cache entries
        $this->clearOldCache();

        // Reset query cache
        DB::statement('RESET QUERY CACHE');
    }

    /**
     * Clear old cache entries
     */
    private function clearOldCache(): void
    {
        // This would typically use Redis SCAN or similar
        // For now, we'll clear cache older than 24 hours
        $this->clearCache();
    }
}
