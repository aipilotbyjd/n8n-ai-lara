<?php

namespace App\Workflow\Execution;

use Exception;

class NodeExecutionResult
{
    private bool $success;
    private array $outputData;
    private ?Exception $exception;
    private array $metadata;
    private float $executionTime;
    private int $dataSize;
    private array $warnings;

    public function __construct(
        bool $success = true,
        array $outputData = [],
        ?Exception $exception = null,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->outputData = $outputData;
        $this->exception = $exception;
        $this->metadata = $metadata;
        $this->executionTime = 0.0;
        $this->dataSize = 0;
        $this->warnings = [];
    }

    public static function success(array $outputData = [], array $metadata = []): self
    {
        return new self(true, $outputData, null, $metadata);
    }

    public static function error(Exception $exception, array $metadata = []): self
    {
        return new self(false, [], $exception, $metadata);
    }

    public static function failure(string $message, array $metadata = []): self
    {
        return new self(false, [], new Exception($message), $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOutputData(): array
    {
        return $this->outputData;
    }

    public function setOutputData(array $data): self
    {
        $this->outputData = $data;
        $this->dataSize = strlen(json_encode($data));
        return $this;
    }

    public function getException(): ?Exception
    {
        return $this->exception;
    }

    public function getErrorMessage(): ?string
    {
        return $this->exception?->getMessage();
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(float $time): self
    {
        $this->executionTime = $time;
        return $this;
    }

    public function getDataSize(): int
    {
        return $this->dataSize;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get the primary output (first item in output data)
     */
    public function getPrimaryOutput()
    {
        return $this->outputData[0] ?? null;
    }

    /**
     * Get output by index
     */
    public function getOutput(int $index)
    {
        return $this->outputData[$index] ?? null;
    }

    /**
     * Get all outputs as collection
     */
    public function getOutputs(): array
    {
        return $this->outputData;
    }

    /**
     * Add output data
     */
    public function addOutput(array $data): self
    {
        $this->outputData[] = $data;
        return $this;
    }

    /**
     * Merge output data
     */
    public function mergeOutputs(array $outputs): self
    {
        $this->outputData = array_merge($this->outputData, $outputs);
        return $this;
    }

    /**
     * Check if result has any output data
     */
    public function hasOutput(): bool
    {
        return !empty($this->outputData);
    }

    /**
     * Get result as array for API responses
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output_data' => $this->outputData,
            'error_message' => $this->getErrorMessage(),
            'metadata' => $this->metadata,
            'execution_time' => $this->executionTime,
            'data_size' => $this->dataSize,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Create result from array (useful for deserialization)
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            $data['success'] ?? true,
            $data['output_data'] ?? [],
            isset($data['error_message']) ? new Exception($data['error_message']) : null,
            $data['metadata'] ?? []
        );

        if (isset($data['execution_time'])) {
            $result->setExecutionTime($data['execution_time']);
        }

        if (isset($data['warnings'])) {
            $result->warnings = $data['warnings'];
        }

        return $result;
    }
}
