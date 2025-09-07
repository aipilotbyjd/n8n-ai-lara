<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class WaitNode implements NodeInterface
{
    public function getId(): string
    {
        return 'wait';
    }

    public function getName(): string
    {
        return 'Wait';
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
        return 'wait';
    }

    public function getDescription(): string
    {
        return 'Add a delay or wait for a specific time before continuing';
    }

    public function getProperties(): array
    {
        return [
            'waitType' => [
                'type' => 'select',
                'options' => ['fixed', 'expression', 'until'],
                'default' => 'fixed',
                'required' => true,
                'description' => 'Type of wait to perform',
            ],
            'waitTime' => [
                'type' => 'number',
                'default' => 5,
                'min' => 0.1,
                'max' => 3600,
                'description' => 'Wait time in seconds (for fixed type)',
                'condition' => 'waitType === "fixed"',
            ],
            'timeUnit' => [
                'type' => 'select',
                'options' => ['seconds', 'minutes', 'hours'],
                'default' => 'seconds',
                'description' => 'Unit for wait time',
                'condition' => 'waitType === "fixed"',
            ],
            'expression' => [
                'type' => 'string',
                'placeholder' => 'Math.random() * 10',
                'description' => 'Expression to calculate wait time (for expression type)',
                'condition' => 'waitType === "expression"',
            ],
            'waitUntil' => [
                'type' => 'datetime',
                'description' => 'Wait until this specific date/time (for until type)',
                'condition' => 'waitType === "until"',
            ],
            'maxWaitTime' => [
                'type' => 'number',
                'default' => 300,
                'min' => 1,
                'max' => 3600,
                'description' => 'Maximum wait time in seconds to prevent infinite waits',
            ],
            'resumeExecution' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Resume workflow execution after wait',
            ],
            'ignoreErrors' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Continue workflow even if wait fails',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data to pass through during wait',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data passed through after wait',
                'properties' => [
                    'waitedFor' => ['type' => 'number'],
                    'waitType' => ['type' => 'string'],
                    'startedAt' => ['type' => 'string'],
                    'completedAt' => ['type' => 'string'],
                ],
            ],
            'timeout' => [
                'type' => 'object',
                'description' => 'Output when wait times out',
                'properties' => [
                    'timedOut' => ['type' => 'boolean'],
                    'waitedFor' => ['type' => 'number'],
                    'maxWaitTime' => ['type' => 'number'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $waitType = isset($properties['waitType']) ? $properties['waitType'] : 'fixed';

        switch ($waitType) {
            case 'fixed':
                $waitTime = isset($properties['waitTime']) ? $properties['waitTime'] : 0;
                if ($waitTime <= 0 || $waitTime > 3600) {
                    return false;
                }
                break;

            case 'expression':
                if (empty($properties['expression'])) {
                    return false;
                }
                break;

            case 'until':
                if (empty($properties['waitUntil'])) {
                    return false;
                }
                // Basic datetime validation
                $dateTime = isset($properties['waitUntil']) ? $properties['waitUntil'] : '';
                if (!empty($dateTime)) {
                    try {
                        new \DateTime($dateTime);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                break;

            default:
                return false;
        }

        $maxWaitTime = isset($properties['maxWaitTime']) ? $properties['maxWaitTime'] : 300;
        if ($maxWaitTime < 1 || $maxWaitTime > 3600) {
            return false;
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $waitType = isset($properties['waitType']) ? $properties['waitType'] : 'fixed';
            $maxWaitTime = isset($properties['maxWaitTime']) ? $properties['maxWaitTime'] : 300;
            $ignoreErrors = isset($properties['ignoreErrors']) ? $properties['ignoreErrors'] : false;

            $context->log("Starting wait", [
                'wait_type' => $waitType,
                'max_wait_time' => $maxWaitTime,
            ]);

            $waitTime = $this->calculateWaitTime($properties, $inputData);
            $actualWaitTime = min($waitTime, $maxWaitTime);

            $startedAt = now();

            $context->log("Waiting for {$actualWaitTime} seconds", [
                'calculated_wait' => $waitTime,
                'actual_wait' => $actualWaitTime,
                'timed_out' => $waitTime > $maxWaitTime,
            ]);

            // In a real implementation, this would either:
            // 1. Sleep for synchronous execution
            // 2. Schedule a job for later execution
            // For now, we'll simulate the wait
            if ($actualWaitTime > 0) {
                // Simulate wait - in production, use proper async scheduling
                sleep(min(1, $actualWaitTime)); // Only sleep for demo purposes
            }

            $completedAt = now();
            $waitedFor = $completedAt->diffInSeconds($startedAt);

            $result = array_merge($inputData, [
                'waitedFor' => $waitedFor,
                'waitType' => $waitType,
                'startedAt' => $startedAt->toISOString(),
                'completedAt' => $completedAt->toISOString(),
            ]);

            // Check if we timed out
            if ($waitTime > $maxWaitTime) {
                $timeoutResult = [
                    'timedOut' => true,
                    'waitedFor' => $waitedFor,
                    'maxWaitTime' => $maxWaitTime,
                    'requestedWait' => $waitTime,
                ];

                $context->log("Wait timed out", [
                    'requested' => $waitTime,
                    'max_allowed' => $maxWaitTime,
                    'actual' => $waitedFor,
                ]);

                if ($ignoreErrors) {
                    return NodeExecutionResult::success([$result]);
                } else {
                    return NodeExecutionResult::success([$timeoutResult], 'timeout');
                }
            }

            $context->log("Wait completed successfully", [
                'waited_for' => $waitedFor,
                'wait_type' => $waitType,
            ]);

            return NodeExecutionResult::success([$result]);

        } catch (\Exception $e) {
            $context->log("Wait failed", [
                'error' => $e->getMessage(),
            ]);

            if (isset($ignoreErrors) && $ignoreErrors) {
                return NodeExecutionResult::success([$inputData]);
            }

            return NodeExecutionResult::error($e);
        }
    }

    private function calculateWaitTime(array $properties, array $inputData): int
    {
        $waitType = isset($properties['waitType']) ? $properties['waitType'] : 'fixed';

        switch ($waitType) {
            case 'fixed':
                $waitTime = isset($properties['waitTime']) ? $properties['waitTime'] : 5;
                $timeUnit = isset($properties['timeUnit']) ? $properties['timeUnit'] : 'seconds';

                switch ($timeUnit) {
                    case 'minutes':
                        return (int)($waitTime * 60);
                    case 'hours':
                        return (int)($waitTime * 3600);
                    default:
                        return (int)$waitTime;
                }

            case 'expression':
                try {
                    $expression = isset($properties['expression']) ? $properties['expression'] : '';
                    return (int)$this->evaluateExpression($expression, $inputData);
                } catch (\Exception $e) {
                    Log::warning("Failed to evaluate wait expression", [
                        'expression' => $expression,
                        'error' => $e->getMessage(),
                    ]);
                    return 5; // Default to 5 seconds
                }

            case 'until':
                try {
                    $waitUntil = isset($properties['waitUntil']) ? $properties['waitUntil'] : '';
                    $targetTime = new \DateTime($waitUntil);
                    $now = new \DateTime();
                    $diff = $targetTime->getTimestamp() - $now->getTimestamp();
                    return max(0, $diff);
                } catch (\Exception $e) {
                    Log::warning("Failed to parse wait until time", [
                        'time' => $waitUntil,
                        'error' => $e->getMessage(),
                    ]);
                    return 5; // Default to 5 seconds
                }

            default:
                return 5;
        }
    }

    private function evaluateExpression(string $expression, array $inputData): float
    {
        // Simple expression evaluation - in production, use a proper JS evaluator
        $expression = trim($expression);

        // Handle simple mathematical expressions
        if (preg_match('/^Math\.random\(\)\s*\*\s*(\d+)$/', $expression, $matches)) {
            return mt_rand(0, (int)$matches[1]);
        }

        if (preg_match('/^(\d+)$/', $expression, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)$/', $expression, $matches)) {
            return (float)$matches[1];
        }

        // Default fallback
        return 5.0;
    }

    public function canHandle(array $inputData): bool
    {
        return true; // Wait node can handle any input
    }

    public function getMaxExecutionTime(): int
    {
        return 3600; // Wait can take up to an hour in theory
    }

    public function getOptions(): array
    {
        return [
            'retryable' => false, // Wait operations are time-sensitive
            'isTrigger' => false,
            'logicNode' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return true; // Wait operations are perfect for async execution
    }

    public function getPriority(): int
    {
        return 4; // Lower priority for wait operations
    }

    public function getTags(): array
    {
        return ['wait', 'delay', 'time', 'schedule', 'logic', 'async'];
    }
}
