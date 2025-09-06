<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Str;

class WebhookTriggerNode implements NodeInterface
{
    public function getId(): string
    {
        return 'webhookTrigger';
    }

    public function getName(): string
    {
        return 'Webhook';
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
        return 'webhook';
    }

    public function getDescription(): string
    {
        return 'Trigger workflow execution via HTTP webhooks';
    }

    public function getProperties(): array
    {
        return [
            'path' => [
                'type' => 'string',
                'placeholder' => '/webhook/my-endpoint',
                'description' => 'Custom webhook path',
                'required' => false,
            ],
            'method' => [
                'type' => 'select',
                'options' => ['GET', 'POST', 'PUT', 'PATCH'],
                'default' => 'POST',
                'required' => true,
            ],
            'authentication' => [
                'type' => 'select',
                'options' => ['none', 'basic', 'bearer', 'api_key'],
                'default' => 'none',
            ],
            'basicAuth' => [
                'type' => 'object',
                'properties' => [
                    'username' => ['type' => 'string'],
                    'password' => ['type' => 'string'],
                ],
                'condition' => 'authentication === "basic"',
            ],
            'bearerToken' => [
                'type' => 'string',
                'condition' => 'authentication === "bearer"',
            ],
            'apiKey' => [
                'type' => 'object',
                'properties' => [
                    'headerName' => ['type' => 'string', 'default' => 'X-API-Key'],
                    'key' => ['type' => 'string'],
                ],
                'condition' => 'authentication === "api_key"',
            ],
            'responseMode' => [
                'type' => 'select',
                'options' => ['immediate', 'delayed'],
                'default' => 'immediate',
                'description' => 'When to respond to the webhook request',
            ],
            'responseCode' => [
                'type' => 'number',
                'default' => 200,
                'min' => 200,
                'max' => 599,
            ],
            'responseBody' => [
                'type' => 'object',
                'description' => 'Custom response body',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Webhook payload data',
                'properties' => [
                    'headers' => ['type' => 'object'],
                    'body' => ['type' => 'object'],
                    'query' => ['type' => 'object'],
                    'method' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'timestamp' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Webhook data for workflow processing',
                'properties' => [
                    'headers' => ['type' => 'object'],
                    'body' => ['type' => 'object'],
                    'query' => ['type' => 'object'],
                    'method' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'timestamp' => ['type' => 'string'],
                    'webhookId' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        // Validate path format if provided
        if (isset($properties['path']) && !empty($properties['path'])) {
            if (!preg_match('/^\/[a-zA-Z0-9\-_\/]*$/', $properties['path'])) {
                return false;
            }
        }

        // Validate method
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH'];
        if (!in_array(strtoupper($properties['method'] ?? 'POST'), $validMethods)) {
            return false;
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();

            // Generate webhook URL if not provided
            $webhookPath = $properties['path'] ?? '/webhook/' . Str::random(16);
            $webhookUrl = url($webhookPath);

            // Prepare webhook data
            $webhookData = [
                'webhookId' => Str::uuid()->toString(),
                'url' => $webhookUrl,
                'method' => $properties['method'] ?? 'POST',
                'headers' => $inputData['headers'] ?? [],
                'body' => $inputData['body'] ?? [],
                'query' => $inputData['query'] ?? [],
                'timestamp' => now()->toISOString(),
                'authentication' => $properties['authentication'] ?? 'none',
            ];

            // Add authentication details if configured
            if (($properties['authentication'] ?? 'none') !== 'none') {
                $webhookData['auth'] = $this->getAuthenticationData($properties);
            }

            $context->log("Webhook trigger activated", [
                'webhook_id' => $webhookData['webhookId'],
                'method' => $webhookData['method'],
                'url' => $webhookUrl,
            ]);

            return NodeExecutionResult::success([$webhookData]);

        } catch (\Exception $e) {
            $context->log("Webhook trigger failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    public function canHandle(array $inputData): bool
    {
        return true; // Webhook can handle any input data
    }

    public function getMaxExecutionTime(): int
    {
        return 30; // Webhooks should be fast
    }

    public function getOptions(): array
    {
        return [
            'retryable' => false, // Webhooks don't need retry logic
            'isTrigger' => true,
            'webhookEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false; // Webhook triggers are synchronous
    }

    public function getPriority(): int
    {
        return 10; // High priority for triggers
    }

    public function getTags(): array
    {
        return ['webhook', 'trigger', 'http', 'api', 'automation'];
    }

    /**
     * Get authentication data based on configuration
     */
    private function getAuthenticationData(array $properties): array
    {
        $auth = $properties['authentication'] ?? 'none';

        switch ($auth) {
            case 'basic':
                return [
                    'type' => 'basic',
                    'username' => $properties['basicAuth']['username'] ?? '',
                    'password' => $properties['basicAuth']['password'] ?? '',
                ];

            case 'bearer':
                return [
                    'type' => 'bearer',
                    'token' => $properties['bearerToken'] ?? '',
                ];

            case 'api_key':
                return [
                    'type' => 'api_key',
                    'header' => $properties['apiKey']['headerName'] ?? 'X-API-Key',
                    'key' => $properties['apiKey']['key'] ?? '',
                ];

            default:
                return ['type' => 'none'];
        }
    }

    /**
     * Generate webhook URL for this trigger
     */
    public function generateWebhookUrl(array $properties, string $workflowId): string
    {
        $path = $properties['path'] ?? "/webhook/{$workflowId}";
        return url($path);
    }

    /**
     * Validate webhook authentication
     */
    public function validateWebhookAuth(array $properties, array $requestHeaders, array $requestData): bool
    {
        $auth = $properties['authentication'] ?? 'none';

        switch ($auth) {
            case 'basic':
                $username = $properties['basicAuth']['username'] ?? '';
                $password = $properties['basicAuth']['password'] ?? '';
                $authHeader = $requestHeaders['authorization'] ?? '';

                if (empty($authHeader) || !str_starts_with($authHeader, 'Basic ')) {
                    return false;
                }

                $encoded = substr($authHeader, 6);
                $decoded = base64_decode($encoded);
                [$reqUsername, $reqPassword] = explode(':', $decoded, 2);

                return $reqUsername === $username && $reqPassword === $password;

            case 'bearer':
                $token = $properties['bearerToken'] ?? '';
                $authHeader = $requestHeaders['authorization'] ?? '';

                return $authHeader === "Bearer {$token}";

            case 'api_key':
                $headerName = $properties['apiKey']['headerName'] ?? 'X-API-Key';
                $expectedKey = $properties['apiKey']['key'] ?? '';
                $providedKey = $requestHeaders[strtolower($headerName)] ?? '';

                return $providedKey === $expectedKey;

            default:
                return true; // No authentication required
        }
    }
}
