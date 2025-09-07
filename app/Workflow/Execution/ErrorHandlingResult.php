<?php

namespace App\Workflow\Execution;

class ErrorHandlingResult
{
    private bool $success;
    private array $data;
    private ?\Throwable $error;
    private array $metadata;

    public function __construct(bool $success, array $data = [], ?\Throwable $error = null, array $metadata = [])
    {
        $this->success = $success;
        $this->data = $data;
        $this->error = $error;
        $this->metadata = $metadata;
    }

    /**
     * Create a successful result
     */
    public static function success(array $data = [], array $metadata = []): self
    {
        return new self(true, $data, null, $metadata);
    }

    /**
     * Create an error result
     */
    public static function error(\Throwable $error, array $metadata = []): self
    {
        return new self(false, [], $error, $metadata);
    }

    /**
     * Check if the result is successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the result is an error
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Get the result data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the error if any
     */
    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    /**
     * Get error message
     */
    public function getErrorMessage(): string
    {
        return $this->error ? $this->error->getMessage() : '';
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadataValue(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Merge data
     */
    public function mergeData(array $additionalData): self
    {
        $this->data = array_merge($this->data, $additionalData);
        return $this;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error ? [
                'message' => $this->error->getMessage(),
                'code' => $this->error->getCode(),
                'type' => get_class($this->error),
            ] : null,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
