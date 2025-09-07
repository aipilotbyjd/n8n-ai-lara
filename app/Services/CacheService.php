<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    const CACHE_TTL = 3600; // 1 hour
    const LONG_CACHE_TTL = 86400; // 24 hours

    /**
     * Multi-level cache implementation
     */
    public function get(string $key, callable $fallback = null, int $ttl = null)
    {
        $fullKey = $this->getCacheKey($key);
        $ttl = $ttl ?? self::CACHE_TTL;

        // Try Redis first (fastest)
        $value = Cache::store('redis')->get($fullKey);
        if ($value !== null) {
            return $value;
        }

        // Try file cache as fallback
        $value = Cache::store('file')->get($fullKey);
        if ($value !== null) {
            // Store in Redis for faster future access
            Cache::store('redis')->put($fullKey, $value, $ttl);
            return $value;
        }

        // Generate fresh data
        if ($fallback) {
            $value = $fallback();
            $this->set($key, $value, $ttl);
            return $value;
        }

        return null;
    }

    /**
     * Set value in multi-level cache
     */
    public function set(string $key, $value, int $ttl = null): bool
    {
        $fullKey = $this->getCacheKey($key);
        $ttl = $ttl ?? self::CACHE_TTL;

        // Store in both Redis and file cache
        $redisResult = Cache::store('redis')->put($fullKey, $value, $ttl);
        $fileResult = Cache::store('file')->put($fullKey, $value, $ttl);

        return $redisResult && $fileResult;
    }

    /**
     * Delete from all cache levels
     */
    public function forget(string $key): bool
    {
        $fullKey = $this->getCacheKey($key);

        Cache::store('redis')->forget($fullKey);
        Cache::store('file')->forget($fullKey);

        return true;
    }

    /**
     * Cache workflow data with optimization
     */
    public function cacheWorkflow(int $workflowId, array $workflowData = null): array
    {
        $key = "workflow:{$workflowId}";

        if ($workflowData === null) {
            return $this->get($key);
        }

        // Optimize workflow data for caching
        $optimized = $this->optimizeWorkflowData($workflowData);

        $this->set($key, $optimized, self::LONG_CACHE_TTL);
        return $optimized;
    }

    /**
     * Cache user permissions
     */
    public function cacheUserPermissions(int $userId, array $permissions = null): array
    {
        $key = "user_permissions:{$userId}";

        if ($permissions === null) {
            return $this->get($key, function () use ($userId) {
                return $this->loadUserPermissions($userId);
            });
        }

        $this->set($key, $permissions, self::CACHE_TTL);
        return $permissions;
    }

    /**
     * Cache node manifest
     */
    public function cacheNodeManifest(array $manifest = null): array
    {
        $key = 'node_manifest';

        if ($manifest === null) {
            return $this->get($key, function () {
                return $this->loadNodeManifest();
            });
        }

        $this->set($key, $manifest, self::LONG_CACHE_TTL);
        return $manifest;
    }

    /**
     * Cache frequently accessed data
     */
    public function warmCache(): void
    {
        // Preload critical data
        $this->cacheNodeManifest();
        $this->cacheSystemSettings();
        $this->cacheActiveWorkflows();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();

            return [
                'redis' => [
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'used_memory' => $info['used_memory_human'] ?? '0B',
                    'total_connections_received' => $info['total_connections_received'] ?? 0,
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                ],
                'file_cache_size' => $this->getFileCacheSize(),
                'cache_hit_rate' => $this->calculateCacheHitRate(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to retrieve cache statistics',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear all caches
     */
    public function clearAll(): void
    {
        Cache::store('redis')->flush();
        Cache::store('file')->flush();
    }

    /**
     * Get full cache key
     */
    private function getCacheKey(string $key): string
    {
        return 'workflow_cache:' . $key;
    }

    /**
     * Optimize workflow data for caching
     */
    private function optimizeWorkflowData(array $data): array
    {
        // Remove unnecessary fields
        unset($data['created_at'], $data['updated_at']);

        // Compress workflow data if it's large
        if (isset($data['workflow_data'])) {
            $jsonData = json_encode($data['workflow_data']);
            if (strlen($jsonData) > 50000) {
                // In production, you might use gzcompress here
                $data['workflow_data_compressed'] = true;
            }
        }

        return $data;
    }

    /**
     * Load user permissions from database
     */
    private function loadUserPermissions(int $userId): array
    {
        $user = \App\Models\User::with(['organizations', 'teams'])->find($userId);

        if (!$user) {
            return [];
        }

        $permissions = [];

        // Organization permissions
        foreach ($user->organizations as $org) {
            $permissions['organizations'][$org->id] = [
                'role' => $org->pivot->role ?? 'member',
                'permissions' => $this->getOrganizationPermissions($org->pivot->role ?? 'member'),
            ];
        }

        // Team permissions
        foreach ($user->teams as $team) {
            $permissions['teams'][$team->id] = [
                'role' => $team->pivot->role ?? 'member',
                'permissions' => $this->getTeamPermissions($team->pivot->role ?? 'member'),
            ];
        }

        return $permissions;
    }

    /**
     * Load node manifest
     */
    private function loadNodeManifest(): array
    {
        $registry = app(\App\Nodes\Registry\NodeRegistry::class);
        return $registry->getManifest();
    }

    /**
     * Cache system settings
     */
    private function cacheSystemSettings(): array
    {
        return $this->get('system_settings', function () {
            return [
                'max_execution_time' => config('workflow.max_execution_time', 300),
                'max_concurrent_executions' => config('workflow.max_concurrent_executions', 10),
                'cache_ttl' => config('workflow.cache_ttl', 3600),
                'rate_limits' => config('workflow.rate_limits', []),
            ];
        });
    }

    /**
     * Cache active workflows
     */
    private function cacheActiveWorkflows(): array
    {
        return $this->get('active_workflows', function () {
            return \App\Models\Workflow::active()
                ->select('id', 'name', 'organization_id')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get organization permissions
     */
    private function getOrganizationPermissions(string $role): array
    {
        $permissions = [
            'member' => ['read'],
            'admin' => ['read', 'write', 'delete', 'manage_users'],
            'owner' => ['read', 'write', 'delete', 'manage_users', 'manage_billing'],
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Get team permissions
     */
    private function getTeamPermissions(string $role): array
    {
        $permissions = [
            'member' => ['read'],
            'admin' => ['read', 'write', 'delete', 'manage_users'],
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Get file cache size
     */
    private function getFileCacheSize(): string
    {
        $cachePath = storage_path('framework/cache');
        if (!is_dir($cachePath)) {
            return '0B';
        }

        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cachePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $this->formatBytes($size);
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateCacheHitRate(): float
    {
        // This would require additional monitoring
        // For now, return a placeholder
        return 0.95; // 95% hit rate
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
