<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNode implements NodeInterface
{
    public function getId(): string
    {
        return 'slack';
    }

    public function getName(): string
    {
        return 'Slack';
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
        return 'slack';
    }

    public function getDescription(): string
    {
        return 'Send messages to Slack channels and users';
    }

    public function getProperties(): array
    {
        return [
            'action' => [
                'type' => 'select',
                'options' => ['sendMessage', 'sendBlock', 'updateMessage', 'deleteMessage'],
                'default' => 'sendMessage',
                'required' => true,
                'description' => 'Action to perform',
            ],
            'webhookUrl' => [
                'type' => 'string',
                'placeholder' => 'https://hooks.slack.com/services/...',
                'description' => 'Slack webhook URL for simple messaging',
                'condition' => 'action === "sendMessage"',
            ],
            'token' => [
                'type' => 'string',
                'placeholder' => 'xoxb-your-slack-token',
                'description' => 'Slack bot token for API access',
                'condition' => 'action !== "sendMessage"',
            ],
            'channel' => [
                'type' => 'string',
                'placeholder' => '#general or C1234567890',
                'description' => 'Channel ID or name to send message to',
                'condition' => 'action !== "sendMessage"',
            ],
            'text' => [
                'type' => 'string',
                'placeholder' => 'Hello from n8n clone!',
                'description' => 'Message text to send',
                'required' => true,
            ],
            'username' => [
                'type' => 'string',
                'placeholder' => 'n8n-bot',
                'description' => 'Username to send message as',
            ],
            'iconEmoji' => [
                'type' => 'string',
                'placeholder' => ':robot_face:',
                'description' => 'Emoji to use as icon',
            ],
            'iconUrl' => [
                'type' => 'string',
                'placeholder' => 'https://example.com/icon.png',
                'description' => 'URL to icon image',
            ],
            'blocks' => [
                'type' => 'array',
                'description' => 'Slack blocks for rich messaging',
                'condition' => 'action === "sendBlock"',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'text' => ['type' => 'object'],
                        'fields' => ['type' => 'array'],
                    ],
                ],
            ],
            'attachments' => [
                'type' => 'array',
                'description' => 'Message attachments',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'color' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'text' => ['type' => 'string'],
                        'fields' => ['type' => 'array'],
                    ],
                ],
            ],
            'threadTs' => [
                'type' => 'string',
                'description' => 'Thread timestamp to reply in thread',
                'condition' => 'action === "sendMessage" || action === "sendBlock"',
            ],
            'messageTs' => [
                'type' => 'string',
                'description' => 'Message timestamp to update/delete',
                'condition' => 'action === "updateMessage" || action === "deleteMessage"',
            ],
            'asUser' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Send as authenticated user instead of bot',
                'condition' => 'action !== "sendMessage"',
            ],
            'linkNames' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Find and link user groups',
            ],
            'unfurlLinks' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Unfurl links in messages',
            ],
            'unfurlMedia' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Unfurl media in messages',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data for dynamic message content',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Slack API response',
                'properties' => [
                    'ok' => ['type' => 'boolean'],
                    'channel' => ['type' => 'string'],
                    'ts' => ['type' => 'string'],
                    'message' => ['type' => 'object'],
                    'error' => ['type' => 'string'],
                ],
            ],
            'error' => [
                'type' => 'object',
                'description' => 'Error information if Slack API call fails',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'code' => ['type' => 'string'],
                    'response' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $action = isset($properties['action']) ? $properties['action'] : 'sendMessage';

        // Validate required fields based on action
        switch ($action) {
            case 'sendMessage':
                if (empty($properties['webhookUrl']) || empty($properties['text'])) {
                    return false;
                }
                break;

            case 'sendBlock':
                if (empty($properties['token']) || empty($properties['channel'])) {
                    return false;
                }
                if (empty($properties['text']) && empty($properties['blocks'])) {
                    return false;
                }
                break;

            case 'updateMessage':
            case 'deleteMessage':
                if (empty($properties['token']) || empty($properties['channel']) || empty($properties['messageTs'])) {
                    return false;
                }
                break;

            default:
                return false;
        }

        // Validate webhook URL format
        if ($action === 'sendMessage' && !empty($properties['webhookUrl'])) {
            if (!filter_var($properties['webhookUrl'], FILTER_VALIDATE_URL)) {
                return false;
            }
            if (!str_contains($properties['webhookUrl'], 'hooks.slack.com')) {
                return false;
            }
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $action = isset($properties['action']) ? $properties['action'] : 'sendMessage';

            $context->log("Executing Slack action", [
                'action' => $action,
                'channel' => isset($properties['channel']) ? $properties['channel'] : 'webhook',
            ]);

            $result = null;

            switch ($action) {
                case 'sendMessage':
                    $result = $this->sendWebhookMessage($properties, $inputData, $context);
                    break;

                case 'sendBlock':
                    $result = $this->sendApiMessage($properties, $inputData, $context);
                    break;

                case 'updateMessage':
                    $result = $this->updateMessage($properties, $inputData, $context);
                    break;

                case 'deleteMessage':
                    $result = $this->deleteMessage($properties, $context);
                    break;

                default:
                    throw new \Exception("Unsupported Slack action: {$action}");
            }

            $context->log("Slack action completed", [
                'action' => $action,
                'success' => isset($result['ok']) ? $result['ok'] : true,
            ]);

            return NodeExecutionResult::success([$result]);

        } catch (\Exception $e) {
            $context->log("Slack action failed", [
                'action' => isset($properties['action']) ? $properties['action'] : 'unknown',
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e, [[
                'message' => $e->getMessage(),
                'action' => isset($properties['action']) ? $properties['action'] : 'unknown',
                'response' => null,
            ]]);
        }
    }

    private function sendWebhookMessage(array $properties, array $inputData, NodeExecutionContext $context): array
    {
        $payload = [
            'text' => isset($properties['text']) ? $properties['text'] : '',
        ];

        // Add optional fields
        if (!empty($properties['username'])) {
            $payload['username'] = $properties['username'];
        }

        if (!empty($properties['iconEmoji'])) {
            $payload['icon_emoji'] = $properties['iconEmoji'];
        }

        if (!empty($properties['iconUrl'])) {
            $payload['icon_url'] = $properties['iconUrl'];
        }

        if (!empty($properties['threadTs'])) {
            $payload['thread_ts'] = $properties['threadTs'];
        }

        if (!empty($properties['attachments'])) {
            $payload['attachments'] = $properties['attachments'];
        }

        // In production, this would make actual HTTP request to Slack
        // For now, simulate success
        return [
            'ok' => true,
            'channel' => 'webhook',
            'ts' => (string)microtime(true),
            'message' => [
                'text' => $payload['text'],
                'username' => isset($payload['username']) ? $payload['username'] : 'n8n-bot',
                'ts' => (string)microtime(true),
            ],
            'simulated' => true,
        ];
    }

    private function sendApiMessage(array $properties, array $inputData, NodeExecutionContext $context): array
    {
        $payload = [
            'channel' => $properties['channel'],
            'text' => isset($properties['text']) ? $properties['text'] : '',
        ];

        // Add optional fields
        if (!empty($properties['username'])) {
            $payload['username'] = $properties['username'];
        }

        if (!empty($properties['threadTs'])) {
            $payload['thread_ts'] = $properties['threadTs'];
        }

        if (!empty($properties['blocks'])) {
            $payload['blocks'] = $properties['blocks'];
        }

        if (!empty($properties['attachments'])) {
            $payload['attachments'] = $properties['attachments'];
        }

        $asUser = isset($properties['asUser']) ? $properties['asUser'] : false;
        if ($asUser) {
            $payload['as_user'] = true;
        }

        $linkNames = isset($properties['linkNames']) ? $properties['linkNames'] : true;
        if (!$linkNames) {
            $payload['link_names'] = false;
        }

        $unfurlLinks = isset($properties['unfurlLinks']) ? $properties['unfurlLinks'] : true;
        if (!$unfurlLinks) {
            $payload['unfurl_links'] = false;
        }

        $unfurlMedia = isset($properties['unfurlMedia']) ? $properties['unfurlMedia'] : true;
        if (!$unfurlMedia) {
            $payload['unfurl_media'] = false;
        }

        // In production, this would make actual HTTP request to Slack API
        // For now, simulate success
        return [
            'ok' => true,
            'channel' => $properties['channel'],
            'ts' => (string)microtime(true),
            'message' => [
                'text' => $payload['text'],
                'ts' => (string)microtime(true),
                'user' => 'U1234567890',
            ],
            'simulated' => true,
        ];
    }

    private function updateMessage(array $properties, array $inputData, NodeExecutionContext $context): array
    {
        $payload = [
            'channel' => $properties['channel'],
            'ts' => $properties['messageTs'],
            'text' => isset($properties['text']) ? $properties['text'] : '',
        ];

        // Add optional fields
        if (!empty($properties['blocks'])) {
            $payload['blocks'] = $properties['blocks'];
        }

        if (!empty($properties['attachments'])) {
            $payload['attachments'] = $properties['attachments'];
        }

        // In production, this would make actual HTTP request to Slack API
        // For now, simulate success
        return [
            'ok' => true,
            'channel' => $properties['channel'],
            'ts' => $properties['messageTs'],
            'message' => [
                'text' => $payload['text'],
                'ts' => $properties['messageTs'],
                'edited' => [
                    'user' => 'U1234567890',
                    'ts' => (string)microtime(true),
                ],
            ],
            'simulated' => true,
        ];
    }

    private function deleteMessage(array $properties, NodeExecutionContext $context): array
    {
        $payload = [
            'channel' => $properties['channel'],
            'ts' => $properties['messageTs'],
        ];

        // In production, this would make actual HTTP request to Slack API
        // For now, simulate success
        return [
            'ok' => true,
            'channel' => $properties['channel'],
            'ts' => $properties['messageTs'],
            'simulated' => true,
        ];
    }

    public function canHandle(array $inputData): bool
    {
        return is_array($inputData);
    }

    public function getMaxExecutionTime(): int
    {
        return 30; // Slack API calls should be quick
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => false,
            'slackEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 5; // Medium priority
    }

    public function getTags(): array
    {
        return ['slack', 'message', 'notification', 'chat', 'webhook', 'api'];
    }
}
