<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class SwitchNode implements NodeInterface
{
    public function getId(): string
    {
        return 'switch';
    }

    public function getName(): string
    {
        return 'Switch';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCategory(): string
    {
        return 'logic';
    }

    public function getIcon(): string
    {
        return 'switch';
    }

    public function getDescription(): string
    {
        return 'Route data based on conditions (if/else logic)';
    }

    public function getProperties(): array
    {
        return [
            'mode' => [
                'type' => 'select',
                'options' => ['single', 'multiple'],
                'default' => 'single',
                'required' => true,
                'description' => 'Whether to route to single or multiple outputs',
            ],
            'conditions' => [
                'type' => 'array',
                'description' => 'List of conditions to evaluate',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Condition name'],
                        'condition' => ['type' => 'string', 'description' => 'JavaScript expression'],
                        'output' => ['type' => 'string', 'description' => 'Output name'],
                    ],
                ],
                'minItems' => 1,
            ],
            'defaultOutput' => [
                'type' => 'string',
                'default' => 'default',
                'description' => 'Default output when no conditions match',
            ],
            'evaluateAll' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Evaluate all conditions (for multiple mode)',
                'condition' => 'mode === "multiple"',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data to evaluate conditions against',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'true' => [
                'type' => 'object',
                'description' => 'Data when condition is true',
            ],
            'false' => [
                'type' => 'object',
                'description' => 'Data when condition is false',
            ],
            'default' => [
                'type' => 'object',
                'description' => 'Default output when no conditions match',
            ],
            // Dynamic outputs will be added based on conditions
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $conditions = isset($properties['conditions']) ? $properties['conditions'] : [];

        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (!isset($condition['condition']) || !isset($condition['output'])) {
                return false;
            }
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $mode = isset($properties['mode']) ? $properties['mode'] : 'single';
            $conditions = isset($properties['conditions']) ? $properties['conditions'] : [];
            $evaluateAll = isset($properties['evaluateAll']) ? $properties['evaluateAll'] : false;

            $context->log("Evaluating switch conditions", [
                'mode' => $mode,
                'condition_count' => count($conditions),
                'evaluate_all' => $evaluateAll,
            ]);

            $results = [];
            $matchedOutputs = [];

            if ($mode === 'single') {
                // Single mode: first matching condition wins
                foreach ($conditions as $condition) {
                    if ($this->evaluateCondition($condition['condition'], $inputData)) {
                        $outputName = isset($condition['output']) ? $condition['output'] : 'true';
                        $results[$outputName] = $inputData;
                        $matchedOutputs[] = $outputName;

                        $context->log("Condition matched", [
                            'condition' => $condition['condition'],
                            'output' => $outputName,
                        ]);
                        break;
                    }
                }

                // If no conditions matched, use default
                if (empty($matchedOutputs)) {
                    $defaultOutput = isset($properties['defaultOutput']) ? $properties['defaultOutput'] : 'default';
                    $results[$defaultOutput] = $inputData;
                    $matchedOutputs[] = $defaultOutput;

                    $context->log("No conditions matched, using default", [
                        'default_output' => $defaultOutput,
                    ]);
                }
            } else {
                // Multiple mode: evaluate all conditions
                foreach ($conditions as $condition) {
                    if ($this->evaluateCondition($condition['condition'], $inputData)) {
                        $outputName = isset($condition['output']) ? $condition['output'] : 'true';
                        $results[$outputName] = $inputData;
                        $matchedOutputs[] = $outputName;

                        $context->log("Condition matched", [
                            'condition' => $condition['condition'],
                            'output' => $outputName,
                        ]);

                        if (!$evaluateAll) {
                            break;
                        }
                    }
                }

                // If no conditions matched in multiple mode, still use default
                if (empty($matchedOutputs)) {
                    $defaultOutput = isset($properties['defaultOutput']) ? $properties['defaultOutput'] : 'default';
                    $results[$defaultOutput] = $inputData;
                    $matchedOutputs[] = $defaultOutput;
                }
            }

            $context->log("Switch execution completed", [
                'matched_outputs' => $matchedOutputs,
                'result_count' => count($results),
            ]);

            return NodeExecutionResult::success($results);

        } catch (\Exception $e) {
            $context->log("Switch execution failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    /**
     * Evaluate a condition expression against input data
     */
    private function evaluateCondition(string $expression, array $data): bool
    {
        try {
            // Simple condition evaluation - in production, use a proper expression evaluator
            return $this->evaluateSimpleCondition($expression, $data);
        } catch (\Exception $e) {
            Log::warning("Failed to evaluate condition", [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Simple condition evaluation for basic expressions
     */
    private function evaluateSimpleCondition(string $expression, array $data): bool
    {
        // Remove common JavaScript syntax that won't work in PHP
        $expression = trim($expression);

        // Handle simple equality checks
        if (preg_match('/^(\w+)\s*==\s*["\']([^"\']+)["\']$/', $expression, $matches)) {
            $field = $matches[1];
            $value = $matches[2];
            return (isset($data[$field]) ? $data[$field] : null) == $value;
        }

        if (preg_match('/^(\w+)\s*===\s*["\']([^"\']+)["\']$/', $expression, $matches)) {
            $field = $matches[1];
            $value = $matches[2];
            return (isset($data[$field]) ? $data[$field] : null) === $value;
        }

        // Handle simple inequality checks
        if (preg_match('/^(\w+)\s*!=\s*["\']([^"\']+)["\']$/', $expression, $matches)) {
            $field = $matches[1];
            $value = $matches[2];
            return (isset($data[$field]) ? $data[$field] : null) != $value;
        }

        // Handle numeric comparisons
        if (preg_match('/^(\w+)\s*([><]=?)\s*(\d+)$/', $expression, $matches)) {
            $field = $matches[1];
            $operator = $matches[2];
            $value = (int)$matches[3];
            $fieldValue = (int)(isset($data[$field]) ? $data[$field] : 0);

            switch ($operator) {
                case '>': return $fieldValue > $value;
                case '<': return $fieldValue < $value;
                case '>=': return $fieldValue >= $value;
                case '<=': return $fieldValue <= $value;
            }
        }

        // Handle boolean checks
        if (preg_match('/^(\w+)$/', $expression, $matches)) {
            $field = $matches[1];
            return (bool)(isset($data[$field]) ? $data[$field] : false);
        }

        if (preg_match('/^!\s*(\w+)$/', $expression, $matches)) {
            $field = $matches[1];
            return !(bool)(isset($data[$field]) ? $data[$field] : false);
        }

        // Handle array contains checks
        if (preg_match('/^(\w+)\.includes\(["\']([^"\']+)["\']\)$/', $expression, $matches)) {
            $field = $matches[1];
            $value = $matches[2];
            $arrayValue = isset($data[$field]) ? $data[$field] : [];
            return is_array($arrayValue) && in_array($value, $arrayValue);
        }

        // Handle string contains checks
        if (preg_match('/^(\w+)\.includes\(["\']([^"\']+)["\']\)$/', $expression, $matches)) {
            $field = $matches[1];
            $value = $matches[2];
            $stringValue = (string)(isset($data[$field]) ? $data[$field] : '');
            return strpos($stringValue, $value) !== false;
        }

        // For complex expressions, return false (would need proper JS evaluator)
        Log::info("Complex condition not supported", ['expression' => $expression]);
        return false;
    }

    public function canHandle(array $inputData): bool
    {
        return is_array($inputData);
    }

    public function getMaxExecutionTime(): int
    {
        return 30; // Switch operations should be fast
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => false,
            'logicNode' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 7; // Medium-high priority for logic nodes
    }

    public function getTags(): array
    {
        return ['switch', 'condition', 'logic', 'if', 'else', 'routing', 'branch'];
    }

    /**
     * Get dynamic outputs based on conditions
     */
    public function getDynamicOutputs(array $properties): array
    {
        $outputs = $this->getOutputs();
        $conditions = isset($properties['conditions']) ? $properties['conditions'] : [];

        foreach ($conditions as $condition) {
            $outputName = isset($condition['output']) ? $condition['output'] : 'true';
            if (!isset($outputs[$outputName])) {
                $outputs[$outputName] = [
                    'type' => 'object',
                    'description' => "Output for condition: " . (isset($condition['name']) ? $condition['name'] : $outputName),
                ];
            }
        }

        return $outputs;
    }
}
