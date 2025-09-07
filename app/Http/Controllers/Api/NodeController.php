<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Nodes\Registry\NodeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    private NodeRegistry $nodeRegistry;

    public function __construct(NodeRegistry $nodeRegistry)
    {
        $this->nodeRegistry = $nodeRegistry;
    }

    /**
     * Get all available nodes
     */
    public function index(Request $request): JsonResponse
    {
        $nodes = $this->nodeRegistry->all();

        // Apply filters
        if ($request->has('category')) {
            $nodes = $nodes->filter(function ($node) use ($request) {
                return $node->getCategory() === $request->category;
            });
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);
            $nodes = $this->nodeRegistry->getByTags($tags);
        }

        if ($request->has('search')) {
            $nodes = $this->nodeRegistry->search($request->search);
        }

        $nodeData = $nodes->map(function ($node) {
            return [
                'id' => $node->getId(),
                'name' => $node->getName(),
                'version' => $node->getVersion(),
                'category' => $node->getCategory(),
                'icon' => $node->getIcon(),
                'description' => $node->getDescription(),
                'tags' => $node->getTags(),
                'supports_async' => $node->supportsAsync(),
                'max_execution_time' => $node->getMaxExecutionTime(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $nodeData,
        ]);
    }

    /**
     * Get node manifest (detailed information)
     */
    public function manifest(): JsonResponse
    {
        $manifest = $this->nodeRegistry->getCachedManifest();

        return response()->json([
            'success' => true,
            'data' => $manifest,
        ]);
    }

    /**
     * Get node categories
     */
    public function categories(): JsonResponse
    {
        $categories = $this->nodeRegistry->getCategories();

        $categoryData = [];
        foreach ($categories as $category) {
            $nodes = $this->nodeRegistry->getByCategory($category);
            $categoryData[] = [
                'name' => $category,
                'count' => $nodes->count(),
                'nodes' => $nodes->pluck('id')->toArray(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $categoryData,
        ]);
    }

    /**
     * Get a specific node
     */
    public function show(string $nodeId): JsonResponse
    {
        $node = $this->nodeRegistry->get($nodeId);

        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => 'Node not found',
            ], 404);
        }

        $nodeData = [
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
            'options' => $node->getOptions(),
            'priority' => $node->getPriority(),
        ];

        return response()->json([
            'success' => true,
            'data' => $nodeData,
        ]);
    }

    /**
     * Get recommended nodes for a specific node
     */
    public function recommendations(string $nodeId): JsonResponse
    {
        $node = $this->nodeRegistry->get($nodeId);

        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => 'Node not found',
            ], 404);
        }

        $recommendedIds = $this->nodeRegistry->getRecommendedNodes($nodeId);
        $recommendedNodes = collect($recommendedIds)->map(function ($id) {
            $node = $this->nodeRegistry->get($id);
            return $node ? [
                'id' => $node->getId(),
                'name' => $node->getName(),
                'category' => $node->getCategory(),
                'icon' => $node->getIcon(),
                'description' => $node->getDescription(),
            ] : null;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $recommendedNodes,
        ]);
    }

    /**
     * Validate node properties
     */
    public function validateProperties(Request $request, string $nodeId): JsonResponse
    {
        $node = $this->nodeRegistry->get($nodeId);

        if (!$node) {
            return response()->json([
                'success' => false,
                'message' => 'Node not found',
            ], 404);
        }

        $properties = $request->get('properties', []);
        $isValid = $node->validateProperties($properties);

        $response = [
            'success' => true,
            'data' => [
                'valid' => $isValid,
                'node_id' => $nodeId,
            ],
        ];

        if (!$isValid) {
            $response['data']['message'] = 'Invalid properties for node: ' . $nodeId;
        }

        return response()->json($response);
    }

    /**
     * Get node statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->nodeRegistry->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Search nodes
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $category = $request->get('category');
        $tags = $request->get('tags') ? explode(',', $request->tags) : null;

        if (empty($query) && empty($category) && empty($tags)) {
            return response()->json([
                'success' => false,
                'message' => 'Search query, category, or tags are required',
            ], 422);
        }

        $nodes = $this->nodeRegistry->all();

        // Apply search filters
        if (!empty($query)) {
            $nodes = $this->nodeRegistry->search($query);
        }

        if (!empty($category)) {
            $nodes = $nodes->filter(function ($node) use ($category) {
                return $node->getCategory() === $category;
            });
        }

        if (!empty($tags)) {
            $nodes = $nodes->filter(function ($node) use ($tags) {
                return !empty(array_intersect($node->getTags(), $tags));
            });
        }

        $results = $nodes->map(function ($node) {
            return [
                'id' => $node->getId(),
                'name' => $node->getName(),
                'category' => $node->getCategory(),
                'icon' => $node->getIcon(),
                'description' => $node->getDescription(),
                'tags' => $node->getTags(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'total' => $results->count(),
                'query' => $query,
                'category' => $category,
                'tags' => $tags,
            ],
        ]);
    }

    /**
     * Get nodes by category
     */
    public function category(string $category): JsonResponse
    {
        $nodes = $this->nodeRegistry->getByCategory($category);

        if ($nodes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found or no nodes in category',
            ], 404);
        }

        $nodeData = $nodes->map(function ($node) {
            return [
                'id' => $node->getId(),
                'name' => $node->getName(),
                'version' => $node->getVersion(),
                'icon' => $node->getIcon(),
                'description' => $node->getDescription(),
                'tags' => $node->getTags(),
                'supports_async' => $node->supportsAsync(),
                'max_execution_time' => $node->getMaxExecutionTime(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $nodeData,
            'meta' => [
                'category' => $category,
                'count' => $nodeData->count(),
            ],
        ]);
    }

    /**
     * Refresh node registry cache
     */
    public function refreshCache(): JsonResponse
    {
        $this->nodeRegistry->clearCache();
        $this->nodeRegistry->autoDiscover();

        $manifest = $this->nodeRegistry->getCachedManifest();

        return response()->json([
            'success' => true,
            'message' => 'Node registry cache refreshed successfully',
            'data' => [
                'total_nodes' => count($manifest),
            ],
        ]);
    }
}
