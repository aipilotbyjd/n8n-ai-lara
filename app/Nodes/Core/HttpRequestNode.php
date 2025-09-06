<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpRequestNode implements NodeInterface
{
    public function getId(): string
    {
        return 'httpRequest';
    }

    public function getName(): string
    {
        return 'HTTP Request';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCategory(): string
    {
        return 'action';
    }

    public function getIcon(): string
    {
        return 'http';
    }

    public function getDescription(): string
    {
        return 'Make HTTP requests to external APIs and web services';
    }

    public function getProperties(): array
    {
        return [
            'method' => [
                'type' => 'select',
                'options' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                'default' => 'GET',
                'required' => true,
            ],
            'url' => [
                'type' => 'string',
                'placeholder' => 'https://api.example.com/endpoint',
                'required' => true,
            ],
            'headers' => [
                'type' => 'object',
                'properties' => [
                    'Content-Type' => ['type' => 'string', 'default' => 'application/json'],
                    'Authorization' => ['type' => 'string'],
                    'User-Agent' => ['type' => 'string'],
                ],
            ],
            'body' => [
                'type' => 'object',
                'description' => 'Request body for POST/PUT/PATCH requests',
            ],
            'queryParameters' => [
                'type' => 'object',
                'description' => 'URL query parameters',
            ],
            'timeout' => [
                'type' => 'number',
                'default' => 30,
                'min' => 1,
                'max' => 300,
            ],
            'followRedirects' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'ignoreSSLErrors' => [
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data to use in the request',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Response data from the HTTP request',
                'properties' => [
                    'status' => ['type' => 'number'],
                    'statusText' => ['type' => 'string'],
                    'headers' => ['type' => 'object'],
                    'body' => ['type' => 'object'],
                    'responseTime' => ['type' => 'number'],
                ],
            ],
            'error' => [
                'type' => 'object',
                'description' => 'Error information if the request fails',
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        // Check required fields
        if (empty($properties['url'])) {
            return false;
        }

        // Validate URL format
        if (!filter_var($properties['url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        // Validate method
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array(strtoupper($properties['method'] ?? 'GET'), $validMethods)) {
            return false;
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $startTime = microtime(true);

        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();

            // Extract configuration
            $method = strtoupper($properties['method'] ?? 'GET');
            $url = $properties['url'];
            $headers = $properties['headers'] ?? [];
            $body = $properties['body'] ?? [];
            $queryParams = $properties['queryParameters'] ?? [];
            $timeout = $properties['timeout'] ?? 30;
            $followRedirects = $properties['followRedirects'] ?? true;
            $ignoreSSLErrors = $properties['ignoreSSLErrors'] ?? false;

            // Merge input data with body if provided
            if (!empty($inputData)) {
                $body = array_merge($body, $inputData);
            }

            // Build HTTP request
            $httpRequest = Http::timeout($timeout);

            if ($followRedirects) {
                $httpRequest = $httpRequest->withoutRedirecting();
            }

            if ($ignoreSSLErrors) {
                $httpRequest = $httpRequest->withoutVerifying();
            }

            // Add headers
            foreach ($headers as $key => $value) {
                $httpRequest = $httpRequest->withHeader($key, $value);
            }

            // Add query parameters
            if (!empty($queryParams)) {
                $httpRequest = $httpRequest->withQueryParameters($queryParams);
            }

            // Execute request based on method
            $response = match ($method) {
                'GET' => $httpRequest->get($url),
                'POST' => $httpRequest->post($url, $body),
                'PUT' => $httpRequest->put($url, $body),
                'PATCH' => $httpRequest->patch($url, $body),
                'DELETE' => $httpRequest->delete($url),
            };

            $executionTime = microtime(true) - $startTime;

            // Prepare response data
            $responseData = [
                'status' => $response->status(),
                'statusText' => $response->reason(),
                'headers' => $response->headers(),
                'body' => $response->json() ?: $response->body(),
                'responseTime' => round($executionTime * 1000, 2), // Convert to milliseconds
                'url' => $url,
                'method' => $method,
            ];

            $context->log("HTTP Request completed", [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'response_time' => $executionTime,
            ]);

            return NodeExecutionResult::success([$responseData]);

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            $context->log("HTTP Request failed", [
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
            ]);

            return NodeExecutionResult::error($e, [
                'execution_time' => $executionTime,
                'node_id' => $context->getNodeId(),
            ]);
        }
    }

    public function canHandle(array $inputData): bool
    {
        return true; // HTTP Request can handle any input data
    }

    public function getMaxExecutionTime(): int
    {
        return 300; // 5 minutes max for HTTP requests
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'maxRetries' => 3,
            'retryDelay' => 1000, // 1 second
        ];
    }

    public function supportsAsync(): bool
    {
        return false; // HTTP requests are typically synchronous
    }

    public function getPriority(): int
    {
        return 1; // Standard priority
    }

    public function getTags(): array
    {
        return ['http', 'api', 'web', 'request', 'rest'];
    }
}
