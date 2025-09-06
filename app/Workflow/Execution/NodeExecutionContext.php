<?php

namespace App\Workflow\Execution;

use App\Models\Workflow;
use App\Models\Execution;
use App\Models\User;
use Illuminate\Support\Collection;

class NodeExecutionContext
{
    private Workflow $workflow;
    private Execution $execution;
    private User $user;
    private string $nodeId;
    private array $nodeData;
    private array $inputData;
    private array $properties;
    private Collection $previousNodes;
    private array $workflowVariables;
    private array $executionVariables;
    private int $executionTimeout;
    private bool $isTestExecution;

    public function __construct(
        Workflow $workflow,
        Execution $execution,
        User $user,
        string $nodeId,
        array $nodeData,
        array $inputData = [],
        array $properties = []
    ) {
        $this->workflow = $workflow;
        $this->execution = $execution;
        $this->user = $user;
        $this->nodeId = $nodeId;
        $this->nodeData = $nodeData;
        $this->inputData = $inputData;
        $this->properties = $properties;
        $this->previousNodes = collect();
        $this->workflowVariables = [];
        $this->executionVariables = [];
        $this->executionTimeout = 300; // 5 minutes default
        $this->isTestExecution = false;
    }

    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    public function getExecution(): Execution
    {
        return $this->execution;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getNodeData(): array
    {
        return $this->nodeData;
    }

    public function getInputData(): array
    {
        return $this->inputData;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $key, $default = null)
    {
        return $this->properties[$key] ?? $default;
    }

    public function hasProperty(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    public function getPreviousNodes(): Collection
    {
        return $this->previousNodes;
    }

    public function addPreviousNode(string $nodeId, array $outputData): self
    {
        $this->previousNodes->put($nodeId, $outputData);
        return $this;
    }

    public function getPreviousNodeOutput(string $nodeId): ?array
    {
        return $this->previousNodes->get($nodeId);
    }

    public function getWorkflowVariables(): array
    {
        return $this->workflowVariables;
    }

    public function setWorkflowVariable(string $key, $value): self
    {
        $this->workflowVariables[$key] = $value;
        return $this;
    }

    public function getWorkflowVariable(string $key, $default = null)
    {
        return $this->workflowVariables[$key] ?? $default;
    }

    public function getExecutionVariables(): array
    {
        return $this->executionVariables;
    }

    public function setExecutionVariable(string $key, $value): self
    {
        $this->executionVariables[$key] = $value;
        return $this;
    }

    public function getExecutionVariable(string $key, $default = null)
    {
        return $this->executionVariables[$key] ?? $default;
    }

    public function getExecutionTimeout(): int
    {
        return $this->executionTimeout;
    }

    public function setExecutionTimeout(int $timeout): self
    {
        $this->executionTimeout = $timeout;
        return $this;
    }

    public function isTestExecution(): bool
    {
        return $this->isTestExecution;
    }

    public function setTestExecution(bool $isTest): self
    {
        $this->isTestExecution = $isTest;
        return $this;
    }

    /**
     * Get node position in workflow
     */
    public function getNodePosition(): array
    {
        return $this->nodeData['position'] ?? ['x' => 0, 'y' => 0];
    }

    /**
     * Get node connections
     */
    public function getNodeConnections(): array
    {
        return $this->nodeData['connections'] ?? [];
    }

    /**
     * Check if node has specific connection
     */
    public function hasConnection(string $type, string $index = '0'): bool
    {
        $connections = $this->getNodeConnections();
        return isset($connections[$type][$index]);
    }

    /**
     * Get connected node IDs for specific connection type
     */
    public function getConnectedNodeIds(string $type, string $index = '0'): array
    {
        $connections = $this->getNodeConnections();
        return $connections[$type][$index] ?? [];
    }

    /**
     * Log execution step for debugging
     */
    public function log(string $message, array $data = []): void
    {
        // Implementation for logging execution steps
        // This could integrate with Laravel's logging system
        \Log::info("Node {$this->nodeId}: {$message}", $data);
    }

    /**
     * Create a copy of this context for sub-executions
     */
    public function createChildContext(string $childNodeId, array $childInputData = []): self
    {
        $childContext = clone $this;
        $childContext->nodeId = $childNodeId;
        $childContext->inputData = $childInputData;
        return $childContext;
    }
}
