<?php

namespace App\Nodes\Registry;

use App\Nodes\Interfaces\NodeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NodeRegistry
{
    private Collection $nodes;
    private array $categories;

    public function __construct()
    {
        $this->nodes = collect();
        $this->categories = [];
        $this->loadCoreNodes();
        $this->autoDiscover();
    }

    /**
     * Register a node in the registry
     */
    public function register(NodeInterface $node): void
    {
        $nodeId = $node->getId();

        if ($this->has($nodeId)) {
            Log::warning("Node {$nodeId} is already registered, skipping registration");
            return;
        }

        $this->nodes->put($nodeId, $node);
        $this->categories[$node->getCategory()][] = $nodeId;

        Log::info("Node {$nodeId} registered successfully");
    }

    /**
     * Unregister a node from the registry
     */
    public function unregister(string $nodeId): bool
    {
        if (!$this->has($nodeId)) {
            return false;
        }

        $node = $this->get($nodeId);
        $category = $node->getCategory();

        $this->nodes->forget($nodeId);

        if (isset($this->categories[$category])) {
            $this->categories[$category] = array_filter(
                $this->categories[$category],
                fn($id) => $id !== $nodeId
            );
        }

        Log::info("Node {$nodeId} unregistered successfully");
        return true;
    }

    /**
     * Get a node by ID
     */
    public function get(string $nodeId): ?NodeInterface
    {
        return $this->nodes->get($nodeId);
    }

    /**
     * Check if a node exists
     */
    public function has(string $nodeId): bool
    {
        return $this->nodes->has($nodeId);
    }

    /**
     * Get all registered nodes
     */
    public function all(): Collection
    {
        return $this->nodes;
    }

    /**
     * Get nodes by category
     */
    public function getByCategory(string $category): Collection
    {
        $nodeIds = $this->categories[$category] ?? [];
        return $this->nodes->only($nodeIds);
    }

    /**
     * Get all available categories
     */
    public function getCategories(): array
    {
        return array_keys($this->categories);
    }

    /**
     * Get nodes by tags
     */
    public function getByTags(array $tags): Collection
    {
        return $this->nodes->filter(function (NodeInterface $node) use ($tags) {
            return !empty(array_intersect($node->getTags(), $tags));
        });
    }

    /**
     * Search nodes by name or description
     */
    public function search(string $query): Collection
    {
        $query = strtolower($query);

        return $this->nodes->filter(function (NodeInterface $node) use ($query) {
            return str_contains(strtolower($node->getName()), $query) ||
                   str_contains(strtolower($node->getDescription()), $query);
        });
    }

    /**
     * Get node manifest (for API responses)
     */
    public function getManifest(): array
    {
        return $this->nodes->map(function (NodeInterface $node) {
            return [
                'id' => $node->getId(),
                'name' => $node->getName(),
                'version' => $node->getVersion(),
                'category' => $node->getCategory(),
                'icon' => $node->getIcon(),
                'description' => $node->getDescription(),
                'properties' => $node->getProperties(),
                'inputs' => $node->getInputs(),
                'outputs' => $node->getOutputs(),
                'tags' => $node->getTags(),
                'supports_async' => $node->supportsAsync(),
                'max_execution_time' => $node->getMaxExecutionTime(),
            ];
        })->values()->toArray();
    }

    /**
     * Get cached manifest
     */
    public function getCachedManifest(): array
    {
        return Cache::remember('node_manifest', 3600, function () {
            return $this->getManifest();
        });
    }

    /**
     * Clear manifest cache
     */
    public function clearCache(): void
    {
        Cache::forget('node_manifest');
    }

    /**
     * Get node statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_nodes' => $this->nodes->count(),
            'categories' => array_map(function ($nodeIds) {
                return count($nodeIds);
            }, $this->categories),
            'categories_count' => count($this->categories),
        ];
    }

    /**
     * Load core nodes
     */
    private function loadCoreNodes(): void
    {
        // Register core nodes
        $coreNodes = [
            \App\Nodes\Core\WebhookTriggerNode::class,
            \App\Nodes\Core\HttpRequestNode::class,
            \App\Nodes\Core\DatabaseQueryNode::class,
            \App\Nodes\Core\EmailNode::class,
            \App\Nodes\Core\ScheduleTriggerNode::class,
            \App\Nodes\Core\DataTransformationNode::class,
            \App\Nodes\Core\SwitchNode::class,
            \App\Nodes\Core\LoopNode::class,
            \App\Nodes\Core\EmailTriggerNode::class,
            \App\Nodes\Core\SlackNode::class,
            \App\Nodes\Core\SetNode::class,
            \App\Nodes\Core\WaitNode::class,
        ];

        foreach ($coreNodes as $nodeClass) {
            if (class_exists($nodeClass)) {
                try {
                    $node = new $nodeClass();
                    $this->register($node);
                } catch (\Throwable $e) {
                    Log::error("Failed to register node {$nodeClass}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Auto-discover nodes from configured directories
     */
    public function autoDiscover(): void
    {
        $directories = [
            app_path('Nodes/Core'),
            app_path('Nodes/Custom'),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = glob($directory . '/*.php');
            foreach ($files as $file) {
                $className = $this->getClassNameFromFile($file);
                if ($className && $this->isValidNodeClass($className)) {
                    try {
                        $node = new $className();
                        $this->register($node);
                    } catch (\Throwable $e) {
                        Log::error("Failed to auto-discover node from {$file}", [
                            'class' => $className,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get class name from PHP file
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return null;
        }

        // Extract namespace and class name
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
        } else {
            return null;
        }

        if (preg_match('/class\s+([^\s]+)/', $content, $classMatch)) {
            $className = $classMatch[1];
        } else {
            return null;
        }

        return $namespace . '\\' . $className;
    }

    /**
     * Check if class is a valid node class
     */
    private function isValidNodeClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);
        return $reflection->implementsInterface(\App\Nodes\Interfaces\NodeInterface::class);
    }

    /**
     * Validate node compatibility
     */
    public function validateNodeCompatibility(string $sourceNodeId, string $targetNodeId): bool
    {
        $sourceNode = $this->get($sourceNodeId);
        $targetNode = $this->get($targetNodeId);

        if (!$sourceNode || !$targetNode) {
            return false;
        }

        $sourceOutputs = $sourceNode->getOutputs();
        $targetInputs = $targetNode->getInputs();

        // Simple compatibility check - can be enhanced
        return !empty($sourceOutputs) && !empty($targetInputs);
    }

    /**
     * Get recommended nodes for a given node
     */
    public function getRecommendedNodes(string $nodeId): array
    {
        $node = $this->get($nodeId);
        if (!$node) {
            return [];
        }

        $category = $node->getCategory();
        $tags = $node->getTags();

        return $this->nodes->filter(function (NodeInterface $n) use ($category, $tags, $nodeId) {
            return $n->getId() !== $nodeId &&
                   ($n->getCategory() === $category || !empty(array_intersect($n->getTags(), $tags)));
        })->take(5)->keys()->toArray();
    }
}
