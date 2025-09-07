<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class EmailTriggerNode implements NodeInterface
{
    public function getId(): string
    {
        return 'emailTrigger';
    }

    public function getName(): string
    {
        return 'Email Trigger';
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
        return 'email';
    }

    public function getDescription(): string
    {
        return 'Trigger workflow when emails are received or sent';
    }

    public function getProperties(): array
    {
        return [
            'triggerType' => [
                'type' => 'select',
                'options' => ['received', 'sent', 'both'],
                'default' => 'received',
                'required' => true,
                'description' => 'When to trigger the workflow',
            ],
            'emailAddress' => [
                'type' => 'string',
                'placeholder' => 'workflow@yourdomain.com',
                'description' => 'Email address to monitor',
                'required' => true,
            ],
            'subjectFilter' => [
                'type' => 'string',
                'placeholder' => 'Order Confirmation',
                'description' => 'Filter emails by subject (optional)',
            ],
            'senderFilter' => [
                'type' => 'string',
                'placeholder' => '@company.com',
                'description' => 'Filter emails by sender domain (optional)',
            ],
            'pollInterval' => [
                'type' => 'number',
                'default' => 60,
                'min' => 30,
                'max' => 3600,
                'description' => 'How often to check for new emails (seconds)',
            ],
            'maxEmails' => [
                'type' => 'number',
                'default' => 10,
                'min' => 1,
                'max' => 100,
                'description' => 'Maximum emails to process per check',
            ],
            'markAsRead' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Mark processed emails as read',
            ],
            'includeAttachments' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include attachment data in the trigger output',
            ],
            'deleteAfterProcessing' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Delete emails after processing (use with caution)',
            ],
            'imapHost' => [
                'type' => 'string',
                'placeholder' => 'imap.gmail.com',
                'description' => 'IMAP server hostname',
                'required' => true,
            ],
            'imapPort' => [
                'type' => 'number',
                'default' => 993,
                'description' => 'IMAP server port',
            ],
            'imapUsername' => [
                'type' => 'string',
                'description' => 'IMAP username/email',
                'required' => true,
            ],
            'imapPassword' => [
                'type' => 'string',
                'description' => 'IMAP password or app password',
                'required' => true,
            ],
            'imapEncryption' => [
                'type' => 'select',
                'options' => ['ssl', 'tls', 'none'],
                'default' => 'ssl',
                'description' => 'IMAP encryption method',
            ],
            'mailbox' => [
                'type' => 'string',
                'default' => 'INBOX',
                'description' => 'Mailbox/folder to monitor',
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
                'description' => 'Email data that triggered the workflow',
                'properties' => [
                    'messageId' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'from' => ['type' => 'string'],
                    'to' => ['type' => 'array'],
                    'cc' => ['type' => 'array'],
                    'bcc' => ['type' => 'array'],
                    'date' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'bodyHtml' => ['type' => 'string'],
                    'attachments' => ['type' => 'array'],
                    'headers' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $required = ['emailAddress', 'imapHost', 'imapUsername', 'imapPassword'];
        foreach ($required as $field) {
            if (empty($properties[$field])) {
                return false;
            }
        }

        $triggerType = isset($properties['triggerType']) ? $properties['triggerType'] : 'received';
        if (!in_array($triggerType, ['received', 'sent', 'both'])) {
            return false;
        }

        $port = isset($properties['imapPort']) ? $properties['imapPort'] : 993;
        if ($port < 1 || $port > 65535) {
            return false;
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();

            // In a real implementation, this would connect to IMAP server
            // For now, we'll simulate email data
            $emailData = $this->simulateEmailData($properties);

            $context->log("Email trigger executed", [
                'email_address' => $properties['emailAddress'],
                'trigger_type' => isset($properties['triggerType']) ? $properties['triggerType'] : 'received',
                'subject' => $emailData['subject'],
            ]);

            return NodeExecutionResult::success([$emailData]);

        } catch (\Exception $e) {
            $context->log("Email trigger failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    private function simulateEmailData(array $properties): array
    {
        // This is a simulation - in production, you'd connect to actual IMAP server
        return [
            'messageId' => '<' . uniqid() . '@example.com>',
            'subject' => 'Test Email Subject',
            'from' => 'sender@example.com',
            'to' => [$properties['emailAddress']],
            'cc' => [],
            'bcc' => [],
            'date' => now()->toISOString(),
            'body' => 'This is the plain text body of the email.',
            'bodyHtml' => '<p>This is the <strong>HTML</strong> body of the email.</p>',
            'attachments' => isset($properties['includeAttachments']) && $properties['includeAttachments']
                ? [['filename' => 'document.pdf', 'size' => 1024, 'mimeType' => 'application/pdf']]
                : [],
            'headers' => [
                'content-type' => 'text/plain',
                'user-agent' => 'Email Client',
                'received' => 'from localhost',
            ],
            'triggerType' => isset($properties['triggerType']) ? $properties['triggerType'] : 'received',
            'processedAt' => now()->toISOString(),
        ];
    }

    public function canHandle(array $inputData): bool
    {
        return true; // Email triggers don't need input data
    }

    public function getMaxExecutionTime(): int
    {
        return 60; // Email processing can take longer
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => true,
            'emailEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 9; // High priority for trigger nodes
    }

    public function getTags(): array
    {
        return ['email', 'trigger', 'imap', 'mail', 'notification', 'automation'];
    }
}
