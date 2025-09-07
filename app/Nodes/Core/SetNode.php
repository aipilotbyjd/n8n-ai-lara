<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class SetNode implements NodeInterface
{
    public function getId(): string
    {
        return 'set';
    }

    public function getName(): string
    {
        return 'Set';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCategory(): string
    {
        return 'transform';
    }

    public function getIcon(): string
    {
        return 'set';
    }

    public function getDescription(): string
    {
        return 'Set multiple values and variables in the workflow';
    }

    public function getProperties(): array
    {
        return [
            'mode' => [
                'type' => 'select',
                'options' => ['manual', 'json', 'expression'],
                'default' => 'manual',
                'required' => true,
                'description' => 'How to set the values',
            ],
            'values' => [
                'type' => 'object',
                'description' => 'Key-value pairs to set (for manual mode)',
                'condition' => 'mode === "manual"',
                'properties' => [
                    'variable1' => ['type' => 'string', 'description' => 'First variable'],
                    'variable2' => ['type' => 'string', 'description' => 'Second variable'],
                ],
            ],
            'jsonData' => [
                'type' => 'string',
                'placeholder' => '{"key": "value", "number": 123}',
                'description' => 'JSON string to parse and set (for json mode)',
                'condition' => 'mode === "json"',
            ],
            'expression' => [
                'type' => 'string',
                'placeholder' => 'Object.assign({}, inputData)',
                'description' => 'JavaScript expression to evaluate (for expression mode)',
                'condition' => 'mode === "expression"',
            ],
            'keepOnlySet' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Keep only the set values, remove everything else',
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Additional options',
                'properties' => [
                    'dotNotation' => [
                        'type' => 'boolean',
                        'default' => true,
                        'description' => 'Support dot notation in keys',
                    ],
                    'overwrite' => [
                        'type' => 'boolean',
                        'default' => true,
                        'description' => 'Overwrite existing values',
                    ],
                ],
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data to merge with set values',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Data with set values',
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $mode = isset($properties['mode']) ? $properties['mode'] : 'manual';

        switch ($mode) {
            case 'manual':
                if (empty($properties['values'])) {
                    return false;
                }
                break;

            case 'json':
                if (empty($properties['jsonData'])) {
                    return false;
                }
                // Basic JSON validation
                $jsonData = isset($properties['jsonData']) ? $properties['jsonData'] : '';
                if (!empty($jsonData)) {
                    json_decode($jsonData);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return false;
                    }
                }
                break;

            case 'expression':
                if (empty($properties['expression'])) {
                    return false;
                }
                break;

            default:
                return false;
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $mode = isset($properties['mode']) ? $properties['mode'] : 'manual';
            $keepOnlySet = isset($properties['keepOnlySet']) ? $properties['keepOnlySet'] : false;

            $context->log("Setting values", [
                'mode' => $mode,
                'keep_only_set' => $keepOnlySet,
            ]);

            $setValues = [];

            switch ($mode) {
                case 'manual':
                    $setValues = isset($properties['values']) ? $properties['values'] : [];
                    break;

                case 'json':
                    $jsonData = isset($properties['jsonData']) ? $properties['jsonData'] : '';
                    $setValues = json_decode($jsonData, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Invalid JSON data: ' . json_last_error_msg());
                    }
                    break;

                case 'expression':
                    $setValues = $this->evaluateExpression($properties['expression'], $inputData);
                    break;
            }

            // Start with input data or empty array based on keepOnlySet
            $result = $keepOnlySet ? [] : $inputData;

            // Apply dot notation support if enabled
            $options = isset($properties['options']) ? $properties['options'] : [];
            $dotNotation = isset($options['dotNotation']) ? $options['dotNotation'] : true;
            $overwrite = isset($options['overwrite']) ? $options['overwrite'] : true;

            if ($dotNotation) {
                $result = $this->setValuesWithDotNotation($result, $setValues, $overwrite);
            } else {
                if ($overwrite) {
                    $result = array_merge($result, $setValues);
                } else {
                    $result = array_merge($setValues, $result);
                }
            }

            $context->log("Values set successfully", [
                'set_count' => count($setValues),
                'result_keys' => array_keys($result),
            ]);

            return NodeExecutionResult::success([$result]);

        } catch (\Exception $e) {
            $context->log("Set operation failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    private function setValuesWithDotNotation(array $data, array $values, bool $overwrite = true): array
    {
        foreach ($values as $key => $value) {
            if (str_contains($key, '.')) {
                // Handle dot notation
                $keys = explode('.', $key);
                $current = &$data;

                foreach ($keys as $i => $k) {
                    if ($i === count($keys) - 1) {
                        // Last key - set the value
                        if ($overwrite || !isset($current[$k])) {
                            $current[$k] = $value;
                        }
                    } else {
                        // Intermediate key - ensure array exists
                        if (!isset($current[$k]) || !is_array($current[$k])) {
                            $current[$k] = [];
                        }
                        $current = &$current[$k];
                    }
                }
            } else {
                // Simple key
                if ($overwrite || !isset($data[$key])) {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    private function evaluateExpression(string $expression, array $inputData): array
    {
        try {
            // Simple expression evaluation - in production, use a proper JS evaluator
            return $this->evaluateSimpleExpression($expression, $inputData);
        } catch (\Exception $e) {
            Log::warning("Failed to evaluate expression", [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function evaluateSimpleExpression(string $expression, array $inputData): array
    {
        // Very basic expression evaluation - replace with proper evaluator
        $expression = trim($expression);

        // Handle simple object assignment
        if (preg_match('/^Object\.assign\(\{\},\s*(\w+)\)$/i', $expression, $matches)) {
            $varName = $matches[1];
            if ($varName === 'inputData') {
                return $inputData;
            }
        }

        // Handle simple property access
        if (preg_match('/^(\w+)\.(\w+)$/', $expression, $matches)) {
            $obj = $matches[1];
            $prop = $matches[2];

            if ($obj === 'inputData' && isset($inputData[$prop])) {
                return [$prop => $inputData[$prop]];
            }
        }

        // Default - return input data
        return $inputData;
    }

    public function canHandle(array $inputData): bool
    {
        return is_array($inputData);
    }

    public function getMaxExecutionTime(): int
    {
        return 10; // Set operations should be very fast
    }

    public function getOptions(): array
    {
        return [
            'retryable' => false, // Set operations are idempotent
            'isTrigger' => false,
            'dataProcessing' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 8; // High priority for data manipulation
    }

    public function getTags(): array
    {
        return ['set', 'variables', 'data', 'transform', 'json', 'expression'];
    }
}
