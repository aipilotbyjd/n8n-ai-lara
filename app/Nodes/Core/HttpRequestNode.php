<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
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
        return 'Make HTTP requests to external APIs and services';
    }

    public function getProperties(): array
    {
        return [
            'method' => [
                'type' => 'select',
                'options' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'default' => 'GET',
                'required' => true,
            ],
            'url' => [
                'type' => 'string',
                'placeholder' => 'https://api.example.com/endpoint',
                'description' => 'The URL to make the request to',
                'required' => true,
            ],
            'headers' => [
                'type' => 'object',
                'description' => 'Request headers',
                'properties' => [
                    'Content-Type' => ['type' => 'string', 'default' => 'application/json'],
                    'User-Agent' => ['type' => 'string', 'default' => 'n8n-clone/1.0'],
                    'Authorization' => ['type' => 'string'],
                ],
            ],
            'queryParameters' => [
                'type' => 'object',
                'description' => 'Query parameters to append to URL',
            ],
            'body' => [
                'type' => 'object',
                'description' => 'Request body data',
                'condition' => 'method !== "GET" && method !== "HEAD"',
            ],
            'bodyType' => [
                'type' => 'select',
                'options' => ['json', 'form-data', 'form-urlencoded', 'raw'],
                'default' => 'json',
                'condition' => 'method !== "GET" && method !== "HEAD"',
            ],
            'timeout' => [
                'type' => 'number',
                'default' => 30,
                'min' => 1,
                'max' => 300,
                'description' => 'Request timeout in seconds',
            ],
            'followRedirects' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Follow HTTP redirects',
            ],
            'ignoreSSLErrors' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Ignore SSL certificate errors',
            ],
            'responseFormat' => [
                'type' => 'select',
                'options' => ['json', 'text', 'binary'],
                'default' => 'json',
                'description' => 'Expected response format',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data for dynamic URL/body construction',
                'properties' => [
                    'url' => ['type' => 'string'],
                    'headers' => ['type' => 'object'],
                    'query' => ['type' => 'object'],
                    'body' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'HTTP response data',
                'properties' => [
                    'statusCode' => ['type' => 'number'],
                    'statusText' => ['type' => 'string'],
                    'headers' => ['type' => 'object'],
                    'body' => ['type' => 'object'],
                    'responseTime' => ['type' => 'number'],
                ],
            ],
            'error' => [
                'type' => 'object',
                'description' => 'Error information if request fails',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'code' => ['type' => 'number'],
                    'response' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        // Validate URL
        if (empty($properties['url'])) {
            return false;
        }

        if (!filter_var($properties['url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        // Validate method
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        if (!in_array(strtoupper($properties['method'] ?? ''), $validMethods)) {
            return false;
        }

        // Validate timeout
        $timeout = $properties['timeout'] ?? 30;
        if ($timeout < 1 || $timeout > 300) {
            return false;
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();

            // Merge input data with properties
            $url = $inputData['url'] ?? $properties['url'];
            $headers = array_merge($properties['headers'] ?? [], $inputData['headers'] ?? []);
            $queryParams = array_merge($properties['queryParameters'] ?? [], $inputData['query'] ?? []);
            $body = $inputData['body'] ?? $properties['body'] ?? [];

            // Build full URL with query parameters
            $url = $this->buildUrl($url, $queryParams);

            $context->log("Making HTTP request", [
                'method' => $properties['method'],
                'url' => $url,
                'timeout' => $properties['timeout'] ?? 30,
            ]);

            // Prepare request options
            $options = [
                'timeout' => $properties['timeout'] ?? 30,
                'allow_redirects' => $properties['followRedirects'] ?? true,
                'verify' => !($properties['ignoreSSLErrors'] ?? false),
                'headers' => $this->prepareHeaders($headers),
            ];

            // Add request body
            if (!in_array(strtoupper($properties['method']), ['GET', 'HEAD'])) {
                $options = array_merge($options, $this->prepareBody($body, $properties['bodyType'] ?? 'json'));
            }

            // Make the request
            $startTime = microtime(true);
            $client = new Client();
            $response = $client->request($properties['method'], $url, $options);
            $responseTime = microtime(true) - $startTime;

            // Process response
            $responseData = $this->processResponse($response, $properties['responseFormat'] ?? 'json', $responseTime);

            $context->log("HTTP request completed", [
                'status_code' => $response->getStatusCode(),
                'response_time' => round($responseTime * 1000, 2) . 'ms',
            ]);

            return NodeExecutionResult::success([$responseData]);

        } catch (RequestException $e) {
            $errorData = $this->processRequestException($e);
            $context->log("HTTP request failed", [
                'error' => $e->getMessage(),
                'status_code' => $errorData['code'] ?? null,
            ]);

            return NodeExecutionResult::error($e, [$errorData]);

        } catch (\Exception $e) {
            $context->log("HTTP request error", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    public function canHandle(array $inputData): bool
    {
        return true; // HTTP node can handle any input data
    }

    public function getMaxExecutionTime(): int
    {
        return 300; // HTTP requests can take up to 5 minutes
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => false,
            'httpEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false; // For now, keep it synchronous
    }

    public function getPriority(): int
    {
        return 5; // Medium priority
    }

    public function getTags(): array
    {
        return ['http', 'api', 'request', 'web', 'rest', 'automation'];
    }

    /**
     * Build full URL with query parameters
     */
    private function buildUrl(string $baseUrl, array $queryParams): string
    {
        if (empty($queryParams)) {
            return $baseUrl;
        }

        $url = $baseUrl;
        $queryString = http_build_query($queryParams);

        if (parse_url($url, PHP_URL_QUERY)) {
            $url .= '&' . $queryString;
        } else {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    /**
     * Prepare headers for the request
     */
    private function prepareHeaders(array $headers): array
    {
        $preparedHeaders = [];

        foreach ($headers as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $preparedHeaders[$key] = (string) $value;
            }
        }

        return $preparedHeaders;
    }

    /**
     * Prepare request body based on content type
     */
    private function prepareBody(array $body, string $bodyType): array
    {
        if (empty($body)) {
            return [];
        }

        switch ($bodyType) {
            case 'json':
                return [
                    'json' => $body,
                ];

            case 'form-data':
                return [
                    'multipart' => array_map(function ($key, $value) {
                        return [
                            'name' => $key,
                            'contents' => is_string($value) ? $value : json_encode($value),
                        ];
                    }, array_keys($body), $body),
                ];

            case 'form-urlencoded':
                return [
                    'form_params' => $body,
                ];

            case 'raw':
                return [
                    'body' => is_string($body) ? $body : json_encode($body),
                ];

            default:
                return [
                    'json' => $body,
                ];
        }
    }

    /**
     * Process HTTP response
     */
    private function processResponse($response, string $format, float $responseTime): array
    {
        $statusCode = $response->getStatusCode();
        $statusText = $response->getReasonPhrase();
        $headers = $this->normalizeHeaders($response->getHeaders());
        $bodyContent = (string) $response->getBody();

        $processedBody = match ($format) {
            'json' => $this->parseJsonResponse($bodyContent),
            'text' => $bodyContent,
            'binary' => base64_encode($bodyContent),
            default => $bodyContent,
        };

        return [
            'statusCode' => $statusCode,
            'statusText' => $statusText,
            'headers' => $headers,
            'body' => $processedBody,
            'responseTime' => round($responseTime * 1000, 2), // Convert to milliseconds
            'size' => strlen($bodyContent),
        ];
    }

    /**
     * Parse JSON response safely
     */
    private function parseJsonResponse(string $content): mixed
    {
        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning("Failed to parse JSON response", [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);
            return $content; // Return as string if JSON parsing fails
        }
    }

    /**
     * Normalize headers array
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[$name] = is_array($values) ? $values[0] : $values;
        }

        return $normalized;
    }

    /**
     * Process request exception
     */
    private function processRequestException(RequestException $e): array
    {
        $errorData = [
            'message' => $e->getMessage(),
            'code' => null,
            'response' => null,
        ];

        $response = $e->getResponse();
        if ($response) {
            $errorData['code'] = $response->getStatusCode();
            $errorData['response'] = [
                'statusCode' => $response->getStatusCode(),
                'statusText' => $response->getReasonPhrase(),
                'headers' => $this->normalizeHeaders($response->getHeaders()),
                'body' => (string) $response->getBody(),
            ];
        }

        return $errorData;
    }
}
