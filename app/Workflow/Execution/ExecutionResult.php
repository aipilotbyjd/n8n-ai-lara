<?php

namespace App\Workflow\Execution;

use Exception;

class ExecutionResult
{
    private bool $success;
    private array $outputData;
    private ?string $errorMessage;
    private ?Exception $exception;

    public function __construct(
        bool $success,
        array $outputData = [],
        ?string $errorMessage = null,
        ?Exception $exception = null
    ) {
        $this->success = $success;
        $this->outputData = $outputData;
        $this->errorMessage = $errorMessage;
        $this->exception = $exception;
    }

    /**
     * Create a successful execution result
     */
    public static function success(array $outputData = []): self
    {
        return new self(true, $outputData, null, null);
    }

    /**
     * Create a failed execution result
     */
    public static function error(Exception $exception): self
    {
        return new self(false, [], $exception->getMessage(), $exception);
    }

    /**
     * Check if execution was successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the output data
     */
    public function getOutputData(): array
    {
        return $this->outputData;
    }

    /**
     * Get the error message
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get the exception
     */
    public function getException(): ?Exception
    {
        return $this->exception;
    }

    /**
     * Merge output data
     */
    public function mergeOutputData(array $data): self
    {
        $this->outputData = array_merge($this->outputData, $data);
        return $this;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output_data' => $this->outputData,
            'error_message' => $this->errorMessage,
            'exception' => $this->exception ? [
                'message' => $this->exception->getMessage(),
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine(),
            ] : null,
        ];
    }
}
