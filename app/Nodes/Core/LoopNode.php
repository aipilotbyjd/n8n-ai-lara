<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class LoopNode implements NodeInterface
{
    public function getId(): string
    {
        return 'loop';
    }

    public function getName(): string
    {
        return 'Loop';
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
        return 'loop';
    }

    public function getDescription(): string
    {
        return 'Iterate over arrays or repeat actions';
    }

    public function getProperties(): array
    {
        return [
            'loopType' => [
                'type' => 'select',
                'options' => ['array', 'count', 'condition'],
                'default' => 'array',
                'required' => true,
                'description' => 'Type of loop to perform',
            ],
            'arrayPath' => [
                'type' => 'string',
                'placeholder' => 'data.items',
                'description' => 'JSON path to array to iterate over',
                'condition' => 'loopType === "array"',
            ],
            'count' => [
                'type' => 'number',
                'default' => 5,
                'min' => 1,
                'max' => 1000,
                'description' => 'Number of iterations',
                'condition' => 'loopType === "count"',
            ],
            'condition' => [
                'type' => 'string',
                'placeholder' => 'index < 10',
                'description' => 'Condition to continue looping (JavaScript expression)',
                'condition' => 'loopType === "condition"',
            ],
            'maxIterations' => [
                'type' => 'number',
                'default' => 100,
                'min' => 1,
                'max' => 10000,
                'description' => 'Maximum number of iterations to prevent infinite loops',
            ],
            'outputMode' => [
                'type' => 'select',
                'options' => ['individual', 'batch', 'last'],
                'default' => 'individual',
                'description' => 'How to output the results',
            ],
            'batchSize' => [
                'type' => 'number',
                'default' => 10,
                'min' => 1,
                'max' => 1000,
                'description' => 'Batch size for batch mode',
                'condition' => 'outputMode === "batch"',
            ],
            'includeIndex' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include iteration index in output',
            ],
            'includeItem' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Include current item in output',
                'condition' => 'loopType === "array"',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data for the loop',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'loop' => [
                'type' => 'object',
                'description' => 'Data for each iteration',
                'properties' => [
                    'item' => ['type' => 'object'],
                    'index' => ['type' => 'number'],
                    'iteration' => ['type' => 'number'],
                    'isLast' => ['type' => 'boolean'],
                ],
            ],
            'completed' => [
                'type' => 'object',
                'description' => 'Loop completion data',
                'properties' => [
                    'totalIterations' => ['type' => 'number'],
                    'results' => ['type' => 'array'],
                    'duration' => ['type' => 'number'],
                ],
            ],
            'error' => [
                'type' => 'object',
                'description' => 'Error information if loop fails',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'iteration' => ['type' => 'number'],
                    'error' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $loopType = $properties['loopType'] ?? '';

        switch ($loopType) {
            case 'array':
                return !empty($properties['arrayPath']);
            case 'count':
                $count = $properties['count'] ?? 0;
                return $count > 0 && $count <= 1000;
            case 'condition':
                return !empty($properties['condition']);
            default:
                return false;
        }
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $loopType = $properties['loopType'];
            $maxIterations = $properties['maxIterations'] ?? 100;
            $outputMode = $properties['outputMode'] ?? 'individual';

            $context->log("Starting loop execution", [
                'loop_type' => $loopType,
                'max_iterations' => $maxIterations,
                'output_mode' => $outputMode,
            ]);

            $iterations = $this->prepareIterations($inputData, $properties);
            $results = [];
            $errors = [];
            $startTime = microtime(true);

            foreach ($iterations as $index => $iterationData) {
                if ($index >= $maxIterations) {
                    $context->log("Loop stopped due to max iterations reached", [
                        'iterations_completed' => $index,
                        'max_iterations' => $maxIterations,
                    ]);
                    break;
                }

                try {
                    $loopItem = $this->createLoopItem($iterationData, $index, count($iterations));

                    if ($outputMode === 'individual') {
                        $results[] = $loopItem;
                    } else {
                        // For batch and last modes, collect all results
                        $results[] = $loopItem;
                    }

                    $context->log("Loop iteration completed", [
                        'iteration' => $index + 1,
                        'total_iterations' => count($iterations),
                    ]);

                } catch (\Exception $e) {
                    $errorData = [
                        'message' => $e->getMessage(),
                        'iteration' => $index + 1,
                        'error' => [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ],
                    ];
                    $errors[] = $errorData;

                    $context->log("Loop iteration failed", [
                        'iteration' => $index + 1,
                        'error' => $e->getMessage(),
                    ]);

                    // Continue with next iteration unless it's a critical error
                    if ($this->isCriticalError($e)) {
                        break;
                    }
                }
            }

            $executionTime = microtime(true) - $startTime;

            // Prepare final output based on mode
            $finalResults = $this->prepareFinalOutput($results, $outputMode, $properties, $executionTime);

            // Add completion data
            $completionData = [
                'totalIterations' => count($results),
                'results' => $results,
                'duration' => round($executionTime * 1000, 2), // milliseconds
                'errors' => $errors,
                'completedAt' => now()->toISOString(),
            ];

            $finalResults['completed'] = $completionData;

            $context->log("Loop execution completed", [
                'total_iterations' => count($results),
                'execution_time' => round($executionTime * 1000, 2) . 'ms',
                'error_count' => count($errors),
            ]);

            return NodeExecutionResult::success($finalResults);

        } catch (\Exception $e) {
            $context->log("Loop execution failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    private function prepareIterations(array $inputData, array $properties): array
    {
        $loopType = $properties['loopType'];

        switch ($loopType) {
            case 'array':
                return $this->prepareArrayIterations($inputData, $properties['arrayPath']);

            case 'count':
                return $this->prepareCountIterations($properties['count']);

            case 'condition':
                return $this->prepareConditionIterations($inputData, $properties['condition'], $properties['maxIterations']);

            default:
                return [];
        }
    }

    private function prepareArrayIterations(array $inputData, string $arrayPath): array
    {
        $arrayData = $this->getValueByPath($inputData, $arrayPath);

        if (!is_array($arrayData)) {
            return [];
        }

        return array_map(function ($item, $index) {
            return [
                'item' => $item,
                'index' => $index,
            ];
        }, $arrayData, array_keys($arrayData));
    }

    private function prepareCountIterations(int $count): array
    {
        $iterations = [];
        for ($i = 0; $i < $count; $i++) {
            $iterations[] = [
                'index' => $i,
                'count' => $count,
            ];
        }
        return $iterations;
    }

    private function prepareConditionIterations(array $inputData, string $condition, int $maxIterations): array
    {
        $iterations = [];
        $index = 0;

        while ($index < $maxIterations) {
            // Evaluate condition
            if (!$this->evaluateCondition($condition, array_merge($inputData, ['index' => $index]))) {
                break;
            }

            $iterations[] = [
                'index' => $index,
                'condition' => $condition,
            ];
            $index++;
        }

        return $iterations;
    }

    private function createLoopItem(array $iterationData, int $index, int $total): array
    {
        $item = [
            'iteration' => $index + 1,
            'index' => $index,
            'isFirst' => $index === 0,
            'isLast' => $index === $total - 1,
            'total' => $total,
            'progress' => $total > 0 ? (($index + 1) / $total) * 100 : 0,
        ];

        // Include original iteration data
        return array_merge($item, $iterationData);
    }

    private function prepareFinalOutput(array $results, string $outputMode, array $properties, float $executionTime): array
    {
        switch ($outputMode) {
            case 'individual':
                // Return all individual results
                return ['loop' => $results];

            case 'batch':
                $batchSize = $properties['batchSize'] ?? 10;
                $batches = array_chunk($results, $batchSize);
                return ['loop' => $batches];

            case 'last':
                // Return only the last result
                $lastResult = end($results);
                return ['loop' => $lastResult ? [$lastResult] : []];

            default:
                return ['loop' => $results];
        }
    }

    private function getValueByPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    private function evaluateCondition(string $condition, array $data): bool
    {
        try {
            // Simple condition evaluation - replace with proper JS evaluator in production
            return $this->evaluateSimpleCondition($condition, $data);
        } catch (\Exception $e) {
            Log::warning("Failed to evaluate loop condition", [
                'condition' => $condition,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function evaluateSimpleCondition(string $condition, array $data): bool
    {
        // Handle simple numeric comparisons
        if (preg_match('/^index\s*([><]=?)\s*(\d+)$/', $condition, $matches)) {
            $operator = $matches[1];
            $value = (int)$matches[2];
            $index = $data['index'] ?? 0;

            switch ($operator) {
                case '>': return $index > $value;
                case '<': return $index < $value;
                case '>=': return $index >= $value;
                case '<=': return $index <= $value;
            }
        }

        // Default to false for unsupported conditions
        return false;
    }

    private function isCriticalError(\Exception $e): bool
    {
        // Define which errors should stop the loop
        $criticalErrors = [
            'OutOfMemoryException',
            'DatabaseConnectionException',
            'AuthenticationException',
        ];

        foreach ($criticalErrors as $criticalError) {
            if (strpos(get_class($e), $criticalError) !== false) {
                return true;
            }
        }

        return false;
    }

    public function canHandle(array $inputData): bool
    {
        return is_array($inputData);
    }

    public function getMaxExecutionTime(): int
    {
        return 300; // Loops can take longer depending on iterations
    }

    public function getOptions(): array
    {
        return [
            'retryable' => false, // Don't retry loops as they might cause infinite loops
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
        return 6; // Medium priority for logic nodes
    }

    public function getTags(): array
    {
        return ['loop', 'iteration', 'array', 'repeat', 'foreach', 'logic'];
    }
}
