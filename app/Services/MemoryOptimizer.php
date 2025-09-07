<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class MemoryOptimizer
{
    /**
     * Process large collections with memory optimization
     */
    public function processLargeCollection(Collection $collection, callable $processor, int $chunkSize = 1000): Collection
    {
        if ($collection->count() <= $chunkSize) {
            return $processor($collection);
        }

        // Convert to lazy collection for memory efficiency
        $lazyCollection = $collection->lazy($chunkSize);

        $results = new Collection();

        $lazyCollection->each(function ($chunk) use ($processor, &$results) {
            $processedChunk = $processor($chunk);
            $results = $results->merge($processedChunk);

            // Force garbage collection
            $this->forceGarbageCollection();
        });

        return $results;
    }

    /**
     * Create memory-efficient file processor
     */
    public function processLargeFile(string $filePath, callable $lineProcessor, int $bufferSize = 8192): array
    {
        $results = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        $buffer = '';
        while (!feof($handle)) {
            $chunk = fread($handle, $bufferSize);
            $buffer .= $chunk;

            // Process complete lines
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                if (!empty(trim($line))) {
                    $results[] = $lineProcessor($line);
                }

                // Yield control periodically to prevent memory issues
                if (count($results) % 1000 === 0) {
                    $this->forceGarbageCollection();
                }
            }
        }

        // Process remaining buffer
        if (!empty(trim($buffer))) {
            $results[] = $lineProcessor($buffer);
        }

        fclose($handle);
        return $results;
    }

    /**
     * Optimize memory usage for API responses
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
     * Create streaming response for large datasets
     */
    public function createStreamingResponse(iterable $data, callable $formatter): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () use ($data, $formatter) {
            foreach ($data as $item) {
                echo $formatter($item);
                flush();

                // Yield control periodically
                if (is_int($key = key($data)) && $key % 100 === 0) {
                    $this->forceGarbageCollection();
                }
            }
        }, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Memory-efficient array processing
     */
    public function processLargeArray(array $data, callable $processor, int $chunkSize = 1000): array
    {
        $results = [];

        foreach (array_chunk($data, $chunkSize) as $chunk) {
            $results = array_merge($results, $processor($chunk));

            // Clean up chunk from memory
            unset($chunk);

            $this->forceGarbageCollection();
        }

        return $results;
    }

    /**
     * Optimize workflow data for memory usage
     */
    public function optimizeWorkflowData(array $workflowData): array
    {
        // Remove unnecessary fields
        $fieldsToRemove = ['created_at', 'updated_at', 'deleted_at'];

        foreach ($fieldsToRemove as $field) {
            unset($workflowData[$field]);
        }

        // Optimize workflow structure
        if (isset($workflowData['workflow_data'])) {
            $workflowData['workflow_data'] = $this->optimizeWorkflowStructure($workflowData['workflow_data']);
        }

        return $workflowData;
    }

    /**
     * Optimize workflow structure for memory efficiency
     */
    private function optimizeWorkflowStructure(array $workflowData): array
    {
        // Remove debug information
        if (isset($workflowData['debug'])) {
            unset($workflowData['debug']);
        }

        // Compress large node data if needed
        if (isset($workflowData['nodes'])) {
            $workflowData['nodes'] = array_map(function ($node) {
                // Remove runtime data that's not needed for storage
                unset($node['runtime'], $node['cache']);
                return $node;
            }, $workflowData['nodes']);
        }

        return $workflowData;
    }

    /**
     * Monitor memory usage
     */
    public function getMemoryStats(): array
    {
        return [
            'current_usage' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
            'available' => $this->getAvailableMemory(),
        ];
    }

    /**
     * Get available memory
     */
    private function getAvailableMemory(): string
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return 'Unlimited';
        }

        $limitBytes = $this->convertToBytes($limit);
        $usedBytes = memory_get_usage(true);

        if ($limitBytes <= $usedBytes) {
            return '0B';
        }

        return $this->formatBytes($limitBytes - $usedBytes);
    }

    /**
     * Convert memory limit to bytes
     */
    private function convertToBytes(string $memory): int
    {
        $unit = strtolower(substr($memory, -1));
        $value = (int)substr($memory, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
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

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Force garbage collection
     */
    private function forceGarbageCollection(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
    }

    /**
     * Create memory-efficient query builder
     */
    public function createEfficientQuery($query, array $options = []): mixed
    {
        // Add memory-efficient query options
        if (isset($options['chunk_size'])) {
            return $query->chunk($options['chunk_size'], function ($chunk) use ($options) {
                if (isset($options['processor'])) {
                    $options['processor']($chunk);
                }

                $this->forceGarbageCollection();
            });
        }

        // Add selective field loading
        if (isset($options['fields'])) {
            $query->select($options['fields']);
        }

        // Add cursor pagination for large datasets
        if (isset($options['use_cursor'])) {
            $query->cursor();
        }

        return $query;
    }

    /**
     * Optimize cache storage
     */
    public function optimizeCacheStorage(array $data): array
    {
        // Remove null values
        $data = array_filter($data, function ($value) {
            return $value !== null;
        });

        // Compress large arrays
        foreach ($data as $key => $value) {
            if (is_array($value) && count($value) > 100) {
                // In production, you might use gzcompress
                $data[$key . '_compressed'] = true;
            }
        }

        return $data;
    }

    /**
     * Memory-efficient file upload handler
     */
    public function handleLargeFileUpload($file, callable $processor): mixed
    {
        $path = $file->getRealPath();
        $size = $file->getSize();

        // For large files, process in chunks
        if ($size > 10 * 1024 * 1024) { // 10MB
            return $this->processLargeFile($path, $processor);
        }

        // For smaller files, load into memory
        return $processor(file_get_contents($path));
    }

    /**
     * Create memory-efficient data export
     */
    public function createEfficientExport(iterable $data, string $format = 'json'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () use ($data, $format) {
            $first = true;

            if ($format === 'json') {
                echo '[';
            }

            foreach ($data as $item) {
                if (!$first) {
                    echo $format === 'json' ? ',' : "\n";
                }

                if ($format === 'json') {
                    echo json_encode($item);
                } else {
                    echo implode(',', $item);
                }

                $first = false;
                flush();

                $this->forceGarbageCollection();
            }

            if ($format === 'json') {
                echo ']';
            }
        }, 200, [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/csv',
            'Content-Disposition' => 'attachment; filename="export.' . $format . '"',
        ]);
    }
}
