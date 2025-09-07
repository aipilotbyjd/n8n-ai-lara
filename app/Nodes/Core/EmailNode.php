<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNode implements NodeInterface
{
    public function getId(): string
    {
        return 'email';
    }

    public function getName(): string
    {
        return 'Send Email';
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
        return 'email';
    }

    public function getDescription(): string
    {
        return 'Send emails via SMTP or other configured mail drivers';
    }

    public function getProperties(): array
    {
        return [
            'to' => [
                'type' => 'string',
                'placeholder' => 'recipient@example.com',
                'description' => 'Recipient email address',
                'required' => true,
            ],
            'cc' => [
                'type' => 'string',
                'placeholder' => 'cc@example.com',
                'description' => 'CC recipients (comma-separated)',
            ],
            'bcc' => [
                'type' => 'string',
                'placeholder' => 'bcc@example.com',
                'description' => 'BCC recipients (comma-separated)',
            ],
            'subject' => [
                'type' => 'string',
                'placeholder' => 'Email Subject',
                'description' => 'Email subject line',
                'required' => true,
            ],
            'body' => [
                'type' => 'string',
                'description' => 'Email body content',
                'required' => true,
            ],
            'body_type' => [
                'type' => 'select',
                'options' => ['text', 'html'],
                'default' => 'html',
                'description' => 'Email body format',
            ],
            'from_email' => [
                'type' => 'string',
                'placeholder' => 'noreply@example.com',
                'description' => 'Sender email address',
            ],
            'from_name' => [
                'type' => 'string',
                'placeholder' => 'Workflow System',
                'description' => 'Sender name',
            ],
            'reply_to' => [
                'type' => 'string',
                'placeholder' => 'reply@example.com',
                'description' => 'Reply-to email address',
            ],
            'attachments' => [
                'type' => 'array',
                'description' => 'File attachments',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'filename' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'mime_type' => ['type' => 'string'],
                    ],
                ],
            ],
            'template' => [
                'type' => 'string',
                'description' => 'Email template to use',
            ],
            'template_data' => [
                'type' => 'object',
                'description' => 'Data for template variables',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Email data from previous nodes',
                'properties' => [
                    'to' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'attachments' => ['type' => 'array'],
                    'template_data' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Email sending result',
                'properties' => [
                    'message_id' => ['type' => 'string'],
                    'sent_at' => ['type' => 'string'],
                    'recipient' => ['type' => 'string'],
                    'success' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        // Validate required fields
        if (empty($properties['to']) || empty($properties['subject']) || empty($properties['body'])) {
            return false;
        }

        // Validate email format
        if (!filter_var($properties['to'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Validate CC emails if provided
        if (!empty($properties['cc'])) {
            $ccEmails = array_map('trim', explode(',', $properties['cc']));
            foreach ($ccEmails as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
            }
        }

        // Validate BCC emails if provided
        if (!empty($properties['bcc'])) {
            $bccEmails = array_map('trim', explode(',', $properties['bcc']));
            foreach ($bccEmails as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
            }
        }

        // Validate from email if provided
        if (!empty($properties['from_email']) && !filter_var($properties['from_email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Validate reply-to email if provided
        if (!empty($properties['reply_to']) && !filter_var($properties['reply_to'], FILTER_VALIDATE_EMAIL)) {
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
            $emailConfig = array_merge($properties, array_filter($inputData));

            $context->log("Sending email", [
                'to' => $emailConfig['to'],
                'subject' => $emailConfig['subject'],
            ]);

            // Send the email
            $result = $this->sendEmail($emailConfig);

            $context->log("Email sent successfully", [
                'message_id' => $result['message_id'] ?? null,
                'recipient' => $emailConfig['to'],
            ]);

            return NodeExecutionResult::success([$result]);

        } catch (\Exception $e) {
            $context->log("Email sending failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    public function canHandle(array $inputData): bool
    {
        return true; // Email node can handle any input data
    }

    public function getMaxExecutionTime(): int
    {
        return 60; // Email sending should be relatively fast
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => false,
            'emailEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false; // For now, keep it synchronous
    }

    public function getPriority(): int
    {
        return 4; // Medium-low priority
    }

    public function getTags(): array
    {
        return ['email', 'mail', 'smtp', 'notification', 'communication', 'automation'];
    }

    /**
     * Send the email using Laravel's Mail facade
     */
    private function sendEmail(array $config): array
    {
        $to = $config['to'];
        $subject = $config['subject'];
        $body = $config['body'];
        $bodyType = $config['body_type'] ?? 'html';

        // Prepare mail configuration
        $mailConfig = [
            'to' => $to,
            'subject' => $subject,
        ];

        // Add CC if provided
        if (!empty($config['cc'])) {
            $mailConfig['cc'] = array_map('trim', explode(',', $config['cc']));
        }

        // Add BCC if provided
        if (!empty($config['bcc'])) {
            $mailConfig['bcc'] = array_map('trim', explode(',', $config['bcc']));
        }

        // Add from if provided
        if (!empty($config['from_email'])) {
            $mailConfig['from'] = [
                $config['from_email'],
                $config['from_name'] ?? null,
            ];
        }

        // Add reply-to if provided
        if (!empty($config['reply_to'])) {
            $mailConfig['reply_to'] = $config['reply_to'];
        }

        // Send email
        Mail::raw($body, function ($message) use ($mailConfig, $bodyType, $config) {
            $message->to($mailConfig['to'])
                    ->subject($mailConfig['subject']);

            if (isset($mailConfig['cc'])) {
                $message->cc($mailConfig['cc']);
            }

            if (isset($mailConfig['bcc'])) {
                $message->bcc($mailConfig['bcc']);
            }

            if (isset($mailConfig['from'])) {
                $message->from($mailConfig['from'][0], $mailConfig['from'][1]);
            }

            if (isset($mailConfig['reply_to'])) {
                $message->replyTo($mailConfig['reply_to']);
            }

            // Set content type
            if ($bodyType === 'html') {
                $message->html($config['body']);
            }

            // Add attachments if provided
            if (!empty($config['attachments'])) {
                foreach ($config['attachments'] as $attachment) {
                    if (isset($attachment['filename'], $attachment['content'])) {
                        $message->attachData(
                            base64_decode($attachment['content']),
                            $attachment['filename'],
                            ['mime' => $attachment['mime_type'] ?? 'application/octet-stream']
                        );
                    }
                }
            }
        });

        return [
            'message_id' => uniqid('email_', true),
            'sent_at' => now()->toISOString(),
            'recipient' => $to,
            'success' => true,
            'subject' => $subject,
        ];
    }
}
