<?php

namespace App\Workflow\Engine;

use App\Jobs\ProcessWorkflowExecution;
use App\Models\Execution;
use App\Models\Workflow;
use App\Nodes\Interfaces\NodeInterface;
use App\Nodes\Registry\NodeRegistry;
use App\Queue\QueueManager;
use App\Workflow\Execution\ExecutionResult;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowExecutionEngine
{
    private NodeRegistry $nodeRegistry;
    private ExecutionTracker $tracker;
    private QueueManager $queueManager;

    public function __construct(
        NodeRegistry $nodeRegistry,
        QueueManager $queueManager = null
    ) {
        $this->nodeRegistry = $nodeRegistry;
        $this->tracker = new ExecutionTracker();
        $this->queueManager = $queueManager ?? app(QueueManager::class);
    }

    /**
     * Dispatch workflow execution to queue (asynchronous)
     */
    public function dispatchWorkflowExecution(
        Workflow $workflow,
        array $triggerData = [],
        string $priority = 'normal'
    ): string {
        // Create execution record first
        $execution = $this->createExecution($workflow);

        Log::info('Dispatching workflow execution to queue', [
            'workflow_id' => $workflow->id,
            'execution_id' => $execution->id,
            'priority' => $priority,
        ]);

        // Dispatch job to queue
        $jobId = $this->queueManager->dispatchWorkflowExecutionWithRecord(
            $workflow,
            $execution->id,
            $triggerData,
            $priority
        );

        return $jobId;
    }

    /**
     * Execute a complete workflow synchronously
     */
    public function executeWorkflowSync(Workflow $workflow, array $triggerData = []): ExecutionResult
    {
        $execution = $this->createExecution($workflow);
        $startTime = microtime(true);

        try {
            Log::info("Starting workflow execution", [
                'workflow_id' => $workflow->id,
                'execution_id' => $execution->id,
            ]);

            // Parse workflow structure
            $workflowStructure = $this->parseWorkflowStructure($workflow);

            // Execute workflow
            $result = $this->executeWorkflowStructure($workflow, $execution, $workflowStructure, $triggerData);

            $executionTime = microtime(true) - $startTime;

            // Update execution record
            $execution->update([
                'status' => $result->isSuccess() ? 'success' : 'error',
                'finished_at' => now(),
                'duration' => (int)($executionTime * 1000), // Convert to milliseconds
                'output_data' => $result->getOutputData(),
                'error_message' => $result->getErrorMessage(),
            ]);

            Log::info("Workflow execution completed", [
                'workflow_id' => $workflow->id,
                'execution_id' => $execution->id,
                'status' => $execution->status,
                'execution_time' => $executionTime,
            ]);

            return $result;

        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error("Workflow execution failed", [
                'workflow_id' => $workflow->id,
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
            ]);

            $execution->update([
                'status' => 'error',
                'finished_at' => now(),
                'duration' => (int)($executionTime * 1000),
                'error_message' => $e->getMessage(),
            ]);

            return ExecutionResult::error($e);
        }
    }

    /**
     * Execute a single node
     */
    public function executeNode(
        Workflow $workflow,
        Execution $execution,
        string $nodeId,
        array $nodeData,
        array $inputData = []
    ): NodeExecutionResult {
        $startTime = microtime(true);

        try {
            // Get node instance
            $nodeType = $nodeData['type'] ?? '';
            $node = $this->nodeRegistry->get($nodeType);

            if (!$node) {
                throw new \Exception("Node type '{$nodeType}' not found in registry");
            }

            // Create execution context
            $context = new NodeExecutionContext(
                $workflow,
                $execution,
                $execution->user,
                $nodeId,
                $nodeData,
                $inputData,
                $nodeData['properties'] ?? []
            );

            // Execute node
            $result = $node->execute($context);

            $executionTime = microtime(true) - $startTime;

            // Track execution
            $this->tracker->trackNodeExecution($execution, $nodeId, $result, $executionTime);

            Log::debug("Node execution completed", [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'execution_time' => $executionTime,
                'success' => $result->isSuccess(),
            ]);

            return $result;

        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error("Node execution failed", [
                'node_id' => $nodeId,
                'node_type' => $nodeData['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
            ]);

            $result = NodeExecutionResult::error($e);
            $this->tracker->trackNodeExecution($execution, $nodeId, $result, $executionTime);

            return $result;
        }
    }

    /**
     * Execute workflow in test mode
     */
    public function executeTestWorkflow(Workflow $workflow, array $testData = []): ExecutionResult
    {
        // Create test execution without saving to database
        $execution = new Execution([
            'workflow_id' => $workflow->id,
            'organization_id' => $workflow->organization_id,
            'user_id' => $workflow->user_id,
            'execution_id' => 'test_' . uniqid(),
            'status' => 'running',
            'mode' => 'manual',
            'input_data' => $testData,
        ]);

        try {
            $workflowStructure = $this->parseWorkflowStructure($workflow);
            return $this->executeWorkflowStructure($workflow, $execution, $workflowStructure, $testData);

        } catch (Throwable $e) {
            return ExecutionResult::error($e);
        }
    }

    /**
     * Parse workflow structure from JSON
     */
    private function parseWorkflowStructure(Workflow $workflow): array
    {
        $workflowData = $workflow->workflow_data ?? [];

        return [
            'nodes' => $workflowData['nodes'] ?? [],
            'connections' => $workflowData['connections'] ?? [],
            'settings' => $workflowData['settings'] ?? [],
        ];
    }

    /**
     * Execute workflow structure
     */
    private function executeWorkflowStructure(
        Workflow $workflow,
        Execution $execution,
        array $workflowStructure,
        array $triggerData
    ): ExecutionResult {
        $nodes = collect($workflowStructure['nodes']);
        $connections = collect($workflowStructure['connections']);

        // Find trigger nodes (nodes with no incoming connections)
        $triggerNodes = $this->findTriggerNodes($nodes, $connections);

        if (empty($triggerNodes)) {
            throw new \Exception("No trigger nodes found in workflow");
        }

        // Execute trigger nodes first
        $results = [];
        foreach ($triggerNodes as $triggerNode) {
            $nodeData = $nodes->firstWhere('id', $triggerNode);

            if ($nodeData) {
                $result = $this->executeNode($workflow, $execution, $triggerNode, $nodeData, $triggerData);
                $results[$triggerNode] = $result;

                if (!$result->isSuccess()) {
                    return ExecutionResult::error(new \Exception("Trigger node '{$triggerNode}' failed: " . $result->getErrorMessage()));
                }
            }
        }

        // Execute subsequent nodes based on connections with optimization
        $executedNodes = array_keys($results);
        $maxIterations = 100; // Prevent infinite loops
        $iteration = 0;

        // Pre-calculate dependencies for better performance
        $nodeDependencies = $this->buildDependencyGraph($connections);
        $readyNodes = $this->findReadyNodes($connections, $executedNodes);

        while ($iteration < $maxIterations && !empty($readyNodes)) {
            $newlyExecutedNodes = [];

            // Execute ready nodes in parallel if possible
            if (count($readyNodes) > 1 && $this->canExecuteInParallel($readyNodes, $connections)) {
                $parallelResults = $this->executeNodesInParallel($workflow, $execution, $readyNodes, $nodes, $results);
                foreach ($parallelResults as $nodeId => $result) {
                    $results[$nodeId] = $result;
                    $newlyExecutedNodes[] = $nodeId;
                }
            } else {
                // Execute nodes sequentially
                foreach ($readyNodes as $targetNodeId) {
                    $result = $this->executeTargetNode($workflow, $execution, $targetNodeId, $nodes, $results, $connections);
                    if ($result) {
                        $results[$targetNodeId] = $result;
                        $newlyExecutedNodes[] = $targetNodeId;
                    }
                }
            }

            if (!empty($newlyExecutedNodes)) {
                $executedNodes = array_merge($executedNodes, $newlyExecutedNodes);
                $readyNodes = $this->findReadyNodes($connections, $executedNodes);
            } else {
                break; // No more nodes to execute
            }

            $iteration++;
        }

        if ($iteration >= $maxIterations) {
            Log::warning("Workflow execution stopped due to maximum iterations reached", [
                'workflow_id' => $workflow->id,
                'execution_id' => $execution->id,
            ]);
        }

        // Collect final results
        $finalResults = [];
        foreach ($results as $nodeId => $result) {
            if ($result->isSuccess()) {
                $finalResults = array_merge($finalResults, $result->getOutputData());
            }
        }

        return ExecutionResult::success($finalResults);
    }

    /**
     * Find trigger nodes (nodes with no incoming connections)
     */
    private function findTriggerNodes(Collection $nodes, Collection $connections): array
    {
        $allNodeIds = $nodes->pluck('id')->toArray();
        $targetNodeIds = $connections->pluck('target')->unique()->toArray();

        return array_diff($allNodeIds, $targetNodeIds);
    }

    /**
     * Create execution record
     */
    private function createExecution(Workflow $workflow): Execution
    {
        return Execution::create([
            'workflow_id' => $workflow->id,
            'organization_id' => $workflow->organization_id,
            'user_id' => $workflow->user_id,
            'execution_id' => uniqid('exec_'),
            'status' => 'running',
            'mode' => 'api',
            'started_at' => now(),
        ]);
    }

    /**
     * Validate workflow structure
     */
    public function validateWorkflow(Workflow $workflow): array
    {
        $errors = [];
        $warnings = [];

        $workflowData = $workflow->workflow_data ?? [];

        if (empty($workflowData['nodes'])) {
            $errors[] = 'Workflow must contain at least one node';
        }

        // Validate each node
        foreach ($workflowData['nodes'] ?? [] as $nodeData) {
            $nodeType = $nodeData['type'] ?? '';

            if (!$this->nodeRegistry->has($nodeType)) {
                $errors[] = "Unknown node type: {$nodeType}";
                continue;
            }

            $node = $this->nodeRegistry->get($nodeType);
            $properties = $nodeData['properties'] ?? [];

            if (!$node->validateProperties($properties)) {
                $errors[] = "Invalid properties for node type: {$nodeType}";
            }
        }

        // Check for circular dependencies
        if ($this->hasCircularDependencies($workflowData)) {
            $errors[] = 'Workflow contains circular dependencies';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Build dependency graph for optimization
     */
    private function buildDependencyGraph(Collection $connections): array
    {
        $graph = [];

        foreach ($connections as $connection) {
            $source = $connection['source'] ?? '';
            $target = $connection['target'] ?? '';

            if (!isset($graph[$target])) {
                $graph[$target] = [];
            }
            $graph[$target][] = $source;
        }

        return $graph;
    }

    /**
     * Find nodes that are ready to execute
     */
    private function findReadyNodes(Collection $connections, array $executedNodes): array
    {
        $readyNodes = [];
        $targetNodes = $connections->pluck('target')->unique()->toArray();

        foreach ($targetNodes as $targetNode) {
            if (in_array($targetNode, $executedNodes)) {
                continue;
            }

            // Check if all source nodes are executed
            $sourceNodes = $connections->where('target', $targetNode)->pluck('source')->toArray();
            $allSourcesExecuted = true;

            foreach ($sourceNodes as $sourceNode) {
                if (!in_array($sourceNode, $executedNodes)) {
                    $allSourcesExecuted = false;
                    break;
                }
            }

            if ($allSourcesExecuted) {
                $readyNodes[] = $targetNode;
            }
        }

        return $readyNodes;
    }

    /**
     * Check if nodes can be executed in parallel
     */
    private function canExecuteInParallel(array $nodeIds, Collection $connections): bool
    {
        // Check if any of the nodes have dependencies on each other
        foreach ($nodeIds as $nodeId) {
            $sourceConnections = $connections->where('source', $nodeId)->pluck('target')->toArray();

            if (array_intersect($nodeIds, $sourceConnections)) {
                return false; // Found dependency within the group
            }
        }

        return true;
    }

    /**
     * Execute multiple nodes in parallel
     */
    private function executeNodesInParallel(Workflow $workflow, Execution $execution, array $nodeIds, Collection $nodes, array $existingResults): array
    {
        $results = [];

        // In a real implementation, you would use proper parallel processing
        // For now, we'll simulate parallel execution
        foreach ($nodeIds as $nodeId) {
            $result = $this->executeTargetNode($workflow, $execution, $nodeId, $nodes, $existingResults, collect([]));
            if ($result) {
                $results[$nodeId] = $result;
            }
        }

        return $results;
    }

    /**
     * Execute a single target node
     */
    private function executeTargetNode(Workflow $workflow, Execution $execution, string $targetNodeId, Collection $nodes, array $results, Collection $connections)
    {
        // Find all source connections for this target
        $sourceConnections = $connections->where('target', $targetNodeId);

        if ($sourceConnections->isEmpty()) {
            return null;
        }

        // Get input data from first successful source
        $inputData = [];
        foreach ($sourceConnections as $connection) {
            $sourceNodeId = $connection['source'] ?? '';
            $sourceResult = $results[$sourceNodeId] ?? null;

            if ($sourceResult && $sourceResult->isSuccess()) {
                $inputData = array_merge($inputData, $sourceResult->getOutputData());
            }
        }

        if (empty($inputData)) {
            return null;
        }

        // Execute the target node
        $targetNodeData = $nodes->firstWhere('id', $targetNodeId);
        if (!$targetNodeData) {
            return null;
        }

        $result = $this->executeNode($workflow, $execution, $targetNodeId, $targetNodeData, $inputData);

        if (!$result->isSuccess()) {
            Log::warning("Node '{$targetNodeId}' execution failed", [
                'error' => $result->getErrorMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Check for circular dependencies in workflow
     */
    private function hasCircularDependencies(array $workflowData): bool
    {
        $connections = $workflowData['connections'] ?? [];
        $graph = [];

        // Build adjacency list
        foreach ($connections as $connection) {
            $source = $connection['source'] ?? '';
            $target = $connection['target'] ?? '';

            if (!isset($graph[$source])) {
                $graph[$source] = [];
            }
            $graph[$source][] = $target;
        }

        // Check for cycles using DFS
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($graph) as $node) {
            if ($this->hasCycle($node, $graph, $visited, $recursionStack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * DFS helper for cycle detection
     */
    private function hasCycle(string $node, array $graph, array &$visited, array &$recursionStack): bool
    {
        if (in_array($node, $recursionStack)) {
            return true;
        }

        if (in_array($node, $visited)) {
            return false;
        }

        $visited[] = $node;
        $recursionStack[] = $node;

        if (isset($graph[$node])) {
            foreach ($graph[$node] as $neighbor) {
                if ($this->hasCycle($neighbor, $graph, $visited, $recursionStack)) {
                    return true;
                }
            }
        }

        array_pop($recursionStack);
        return false;
    }
}
