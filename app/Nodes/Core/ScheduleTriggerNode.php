<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class ScheduleTriggerNode implements NodeInterface
{
    public function getId(): string
    {
        return 'scheduleTrigger';
    }

    public function getName(): string
    {
        return 'Schedule Trigger';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCategory(): string
    {
        return 'trigger';
    }

    public function getIcon(): string
    {
        return 'schedule';
    }

    public function getDescription(): string
    {
        return 'Trigger workflow execution on a schedule (cron-like)';
    }

    public function getProperties(): array
    {
        return [
            'scheduleType' => [
                'type' => 'select',
                'options' => ['interval', 'cron', 'specific'],
                'default' => 'interval',
                'required' => true,
                'description' => 'Type of scheduling to use',
            ],
            'interval' => [
                'type' => 'number',
                'default' => 60,
                'min' => 1,
                'max' => 86400,
                'description' => 'Interval in seconds (for interval type)',
                'condition' => 'scheduleType === "interval"',
            ],
            'intervalUnit' => [
                'type' => 'select',
                'options' => ['seconds', 'minutes', 'hours', 'days'],
                'default' => 'minutes',
                'description' => 'Unit for interval',
                'condition' => 'scheduleType === "interval"',
            ],
            'cronExpression' => [
                'type' => 'string',
                'placeholder' => '*/5 * * * *',
                'description' => 'Cron expression (for cron type)',
                'condition' => 'scheduleType === "cron"',
            ],
            'specificTime' => [
                'type' => 'datetime',
                'description' => 'Specific date and time to execute',
                'condition' => 'scheduleType === "specific"',
            ],
            'timezone' => [
                'type' => 'string',
                'default' => 'UTC',
                'description' => 'Timezone for scheduling',
            ],
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Whether this schedule is enabled',
            ],
            'maxExecutions' => [
                'type' => 'number',
                'default' => 0,
                'min' => 0,
                'description' => 'Maximum number of executions (0 = unlimited)',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Scheduled trigger data',
                'properties' => [
                    'timestamp' => ['type' => 'string'],
                    'scheduledTime' => ['type' => 'string'],
                    'executionCount' => ['type' => 'number'],
                    'nextExecution' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $scheduleType = $properties['scheduleType'] ?? '';

        switch ($scheduleType) {
            case 'interval':
                $interval = $properties['interval'] ?? 0;
                if ($interval < 1 || $interval > 86400) {
                    return false;
                }
                break;

            case 'cron':
                $cron = $properties['cronExpression'] ?? '';
                if (empty($cron) || !$this->isValidCronExpression($cron)) {
                    return false;
                }
                break;

            case 'specific':
                $time = $properties['specificTime'] ?? '';
                if (empty($time)) {
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

            $triggerData = [
                'timestamp' => now()->toISOString(),
                'scheduledTime' => now()->toISOString(),
                'executionCount' => 1, // This would be tracked externally
                'scheduleType' => $properties['scheduleType'],
                'timezone' => $properties['timezone'] ?? 'UTC',
            ];

            // Add schedule-specific data
            switch ($properties['scheduleType']) {
                case 'interval':
                    $triggerData['interval'] = $properties['interval'];
                    $triggerData['intervalUnit'] = $properties['intervalUnit'];
                    $triggerData['nextExecution'] = $this->calculateNextIntervalExecution(
                        $properties['interval'],
                        $properties['intervalUnit']
                    );
                    break;

                case 'cron':
                    $triggerData['cronExpression'] = $properties['cronExpression'];
                    $triggerData['nextExecution'] = $this->calculateNextCronExecution(
                        $properties['cronExpression']
                    );
                    break;

                case 'specific':
                    $triggerData['scheduledTime'] = $properties['specificTime'];
                    $triggerData['nextExecution'] = null; // One-time execution
                    break;
            }

            $context->log("Schedule trigger executed", [
                'schedule_type' => $properties['scheduleType'],
                'timestamp' => $triggerData['timestamp'],
            ]);

            return NodeExecutionResult::success([$triggerData]);

        } catch (\Exception $e) {
            $context->log("Schedule trigger failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    public function canHandle(array $inputData): bool
    {
        return true; // Schedule triggers don't need input data
    }

    public function getMaxExecutionTime(): int
    {
        return 30; // Schedule triggers should execute quickly
    }

    public function getOptions(): array
    {
        return [
            'retryable' => false,
            'isTrigger' => true,
            'scheduleEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 10; // High priority for trigger nodes
    }

    public function getTags(): array
    {
        return ['schedule', 'trigger', 'cron', 'interval', 'time', 'automation'];
    }

    /**
     * Check if a cron expression is valid
     */
    private function isValidCronExpression(string $expression): bool
    {
        // Basic validation - in a real implementation, you'd use a proper cron parser
        $parts = explode(' ', $expression);
        return count($parts) === 5;
    }

    /**
     * Calculate next execution time for interval schedules
     */
    private function calculateNextIntervalExecution(int $interval, string $unit): string
    {
        $now = now();

        switch ($unit) {
            case 'seconds':
                return $now->addSeconds($interval)->toISOString();
            case 'minutes':
                return $now->addMinutes($interval)->toISOString();
            case 'hours':
                return $now->addHours($interval)->toISOString();
            case 'days':
                return $now->addDays($interval)->toISOString();
            default:
                return $now->addMinutes($interval)->toISOString();
        }
    }

    /**
     * Calculate next execution time for cron schedules
     */
    private function calculateNextCronExecution(string $expression): string
    {
        // Simplified - in production, use a proper cron library
        // For now, return next hour
        return now()->addHour()->toISOString();
    }
}
