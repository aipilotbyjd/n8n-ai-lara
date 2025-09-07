<?php

namespace App\Workflow\Execution;

use App\Models\Execution;
use App\Models\Workflow;
use Illuminate\Support\Facades\Log;

class ErrorHandler
{
    private array $errorWorkflows = [];

    public function __construct()
    {
        $this->loadErrorWorkflows();
    }

    /**
     * Handle execution error
     */
    public function handleError(Execution $execution, \Throwable $error, array $context = []): ErrorHandlingResult
    {
        Log::error("Handling workflow execution error", [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'error' => $error->getMessage(),
            'error_type' => get_class($error),
        ]);

        // Try to find an error workflow that matches this error
        $errorWorkflow = $this->findMatchingErrorWorkflow($execution, $error);

        if ($errorWorkflow) {
            return $this->executeErrorWorkflow($execution, $error, $errorWorkflow, $context);
        }

        // No error workflow found, return default error result
        return ErrorHandlingResult::error($error, [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'error_workflow_found' => false,
        ]);
    }

    /**
     * Register an error workflow
     */
    public function registerErrorWorkflow(string $errorType, string $workflowId, array $conditions = []): void
    {
        $this->errorWorkflows[$errorType][] = [
            'workflow_id' => $workflowId,
            'conditions' => $conditions,
        ];

        Log::info("Error workflow registered", [
            'error_type' => $errorType,
            'workflow_id' => $workflowId,
            'conditions' => $conditions,
        ]);
    }

    /**
     * Find matching error workflow for the given error
     */
    private function findMatchingErrorWorkflow(Execution $execution, \Throwable $error): ?array
    {
        $errorType = get_class($error);
        $errorMessage = $error->getMessage();

        // Check for exact error type match
        if (isset($this->errorWorkflows[$errorType])) {
            foreach ($this->errorWorkflows[$errorType] as $errorWorkflow) {
                if ($this->matchesConditions($errorWorkflow['conditions'], $execution, $error)) {
                    return $errorWorkflow;
                }
            }
        }

        // Check for parent class matches
        foreach ($this->errorWorkflows as $registeredType => $workflows) {
            if (is_subclass_of($errorType, $registeredType)) {
                foreach ($workflows as $errorWorkflow) {
                    if ($this->matchesConditions($errorWorkflow['conditions'], $execution, $error)) {
                        return $errorWorkflow;
                    }
                }
            }
        }

        // Check for generic Exception handler
        if (isset($this->errorWorkflows[\Exception::class])) {
            foreach ($this->errorWorkflows[\Exception::class] as $errorWorkflow) {
                if ($this->matchesConditions($errorWorkflow['conditions'], $execution, $error)) {
                    return $errorWorkflow;
                }
            }
        }

        return null;
    }

    /**
     * Check if error workflow conditions match
     */
    private function matchesConditions(array $conditions, Execution $execution, \Throwable $error): bool
    {
        if (empty($conditions)) {
            return true; // No conditions means it matches everything
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $execution, $error)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition(array $condition, Execution $execution, \Throwable $error): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';

        $actualValue = null;

        // Get the actual value based on the field
        switch ($field) {
            case 'workflow_id':
                $actualValue = $execution->workflow_id;
                break;
            case 'error_message':
                $actualValue = $error->getMessage();
                break;
            case 'error_code':
                $actualValue = $error->getCode();
                break;
            case 'execution_mode':
                $actualValue = $execution->mode;
                break;
            case 'user_id':
                $actualValue = $execution->user_id;
                break;
            default:
                return false;
        }

        // Evaluate the condition
        switch ($operator) {
            case 'equals':
                return $actualValue == $value;
            case 'not_equals':
                return $actualValue != $value;
            case 'contains':
                return is_string($actualValue) && str_contains($actualValue, $value);
            case 'starts_with':
                return is_string($actualValue) && str_starts_with($actualValue, $value);
            case 'ends_with':
                return is_string($actualValue) && str_ends_with($actualValue, $value);
            case 'greater_than':
                return is_numeric($actualValue) && $actualValue > $value;
            case 'less_than':
                return is_numeric($actualValue) && $actualValue < $value;
            default:
                return false;
        }
    }

    /**
     * Execute error workflow
     */
    private function executeErrorWorkflow(Execution $execution, \Throwable $error, array $errorWorkflow, array $context): ErrorHandlingResult
    {
        try {
            $errorWorkflowId = $errorWorkflow['workflow_id'];

            Log::info("Executing error workflow", [
                'error_workflow_id' => $errorWorkflowId,
                'original_execution_id' => $execution->id,
                'original_error' => $error->getMessage(),
            ]);

            // Prepare error context data
            $errorContext = [
                'original_execution' => [
                    'id' => $execution->id,
                    'workflow_id' => $execution->workflow_id,
                    'user_id' => $execution->user_id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at?->toISOString(),
                ],
                'error' => [
                    'message' => $error->getMessage(),
                    'code' => $error->getCode(),
                    'type' => get_class($error),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                ],
                'context' => $context,
            ];

            // In a real implementation, this would trigger the error workflow
            // For now, we'll simulate success
            return ErrorHandlingResult::success($errorContext, [
                'error_workflow_id' => $errorWorkflowId,
                'handled' => true,
            ]);

        } catch (\Throwable $workflowError) {
            Log::error("Error workflow execution failed", [
                'error_workflow_id' => $errorWorkflowId,
                'error' => $workflowError->getMessage(),
            ]);

            return ErrorHandlingResult::error($workflowError, [
                'error_workflow_id' => $errorWorkflowId,
                'original_error' => $error->getMessage(),
            ]);
        }
    }

    /**
     * Load predefined error workflows from configuration
     */
    private function loadErrorWorkflows(): void
    {
        // Load error workflows from config or database
        // This is a placeholder for actual implementation
        $this->errorWorkflows = config('workflow.error_workflows', []);
    }

    /**
     * Get all registered error workflows
     */
    public function getRegisteredErrorWorkflows(): array
    {
        return $this->errorWorkflows;
    }

    /**
     * Clear all registered error workflows
     */
    public function clearErrorWorkflows(): void
    {
        $this->errorWorkflows = [];
    }
}
