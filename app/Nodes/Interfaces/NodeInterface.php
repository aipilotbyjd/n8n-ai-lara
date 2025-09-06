<?php

namespace App\Nodes\Interfaces;

use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;

interface NodeInterface
{
    /**
     * Get the unique identifier for this node type
     */
    public function getId(): string;

    /**
     * Get the display name for this node
     */
    public function getName(): string;

    /**
     * Get the version of this node
     */
    public function getVersion(): string;

    /**
     * Get the category this node belongs to
     */
    public function getCategory(): string;

    /**
     * Get the icon identifier for this node
     */
    public function getIcon(): string;

    /**
     * Get the description of what this node does
     */
    public function getDescription(): string;

    /**
     * Get the properties/configuration schema for this node
     */
    public function getProperties(): array;

    /**
     * Get the input schema for this node
     */
    public function getInputs(): array;

    /**
     * Get the output schema for this node
     */
    public function getOutputs(): array;

    /**
     * Validate the properties for this node
     */
    public function validateProperties(array $properties): bool;

    /**
     * Execute the node with the given context
     */
    public function execute(NodeExecutionContext $context): NodeExecutionResult;

    /**
     * Check if this node can handle the given input data
     */
    public function canHandle(array $inputData): bool;

    /**
     * Get the maximum execution time for this node in seconds
     */
    public function getMaxExecutionTime(): int;

    /**
     * Get the node configuration options
     */
    public function getOptions(): array;

    /**
     * Check if this node supports asynchronous execution
     */
    public function supportsAsync(): bool;

    /**
     * Get the priority level for this node (higher = executed first)
     */
    public function getPriority(): int;

    /**
     * Get the node tags for categorization
     */
    public function getTags(): array;
}
