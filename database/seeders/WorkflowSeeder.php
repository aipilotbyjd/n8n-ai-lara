<?php

namespace Database\Seeders;

use App\Models\Execution;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();
        $users = User::all();

        if ($organizations->isEmpty() || $users->isEmpty()) {
            return;
        }

        $this->createSampleWorkflows($organizations, $users);
        $this->createTemplateWorkflows();
    }

    private function createSampleWorkflows($organizations, $users): void
    {
        // Customer Support Ticket Processor workflow
        foreach ($organizations as $organization) {
            $userId = $organization->owner_id;
            $teamId = $organization->teams()->inRandomOrder()->first()->id ?? null;

            Workflow::create([
                'name' => 'Customer Support Ticket Processor',
                'slug' => 'customer-support-ticket-processor-' . $organization->id . '-' . rand(1000, 9999),
                'description' => 'Automatically processes customer support tickets, categorizes them, and notifies relevant teams',
                'organization_id' => $organization->id,
                'team_id' => $teamId,
                'user_id' => $userId,
                'workflow_data' => [
                    'nodes' => [
                        [
                            'id' => 'webhook_trigger',
                            'type' => 'webhookTrigger',
                            'position' => ['x' => 100, 'y' => 100],
                            'properties' => [
                                'path' => '/support/ticket',
                                'method' => 'POST',
                                'authentication' => 'none',
                            ],
                        ],
                        [
                            'id' => 'data_processor',
                            'type' => 'databaseQuery',
                            'position' => ['x' => 350, 'y' => 100],
                            'properties' => [
                                'query_type' => 'insert',
                                'table' => 'support_tickets',
                                'parameters' => [
                                    'title' => '{{input.body.title}}',
                                    'description' => '{{input.body.description}}',
                                    'priority' => '{{input.body.priority}}',
                                    'customer_email' => '{{input.body.customer_email}}',
                                    'status' => 'open',
                                    'created_at' => '{{timestamp}}',
                                ],
                            ],
                        ],
                        [
                            'id' => 'email_notification',
                            'type' => 'email',
                            'position' => ['x' => 600, 'y' => 100],
                            'properties' => [
                                'to' => 'support@company.com',
                                'subject' => 'New Support Ticket: {{input.body.title}}',
                                'body' => 'A new support ticket has been created:

Title: {{input.body.title}}
Description: {{input.body.description}}
Priority: {{input.body.priority}}
Customer: {{input.body.customer_email}}

Please review and assign to the appropriate team.',
                                'body_type' => 'text',
                            ],
                        ],
                    ],
                    'connections' => [
                        [
                            'source' => 'webhook_trigger',
                            'target' => 'data_processor',
                            'sourceOutput' => 'main',
                            'targetInput' => 'main',
                        ],
                        [
                            'source' => 'data_processor',
                            'target' => 'email_notification',
                            'sourceOutput' => 'main',
                            'targetInput' => 'main',
                        ],
                    ],
                    'settings' => [
                        'errorHandling' => 'continue',
                        'maxRetries' => 3,
                    ],
                ],
                'settings' => [
                    'webhook_response_code' => 200,
                    'webhook_response_body' => ['status' => 'success', 'message' => 'Ticket created successfully'],
                ],
                'status' => 'published',
                'is_active' => true,
                'tags' => ['support', 'automation', 'customer-service'],
            ]);
        }
    }

    private function createTemplateWorkflows(): void
    {
        $organizations = Organization::all();
        if ($organizations->isEmpty()) {
            return; // Skip if no organizations exist
        }

        $templateWorkflows = [
            [
                'name' => 'HTTP Request Template',
                'description' => 'Template for making HTTP requests to external APIs',
                'workflow_data' => [
                    'nodes' => [
                        [
                            'id' => 'start',
                            'type' => 'webhookTrigger',
                            'position' => ['x' => 100, 'y' => 100],
                            'properties' => [
                                'path' => '/api/request',
                                'method' => 'POST',
                            ],
                        ],
                        [
                            'id' => 'http_request',
                            'type' => 'httpRequest',
                            'position' => ['x' => 350, 'y' => 100],
                            'properties' => [
                                'method' => 'GET',
                                'url' => 'https://api.example.com/endpoint',
                            ],
                        ],
                    ],
                    'connections' => [
                        [
                            'source' => 'start',
                            'target' => 'http_request',
                            'sourceOutput' => 'main',
                            'targetInput' => 'main',
                        ],
                    ],
                ],
                'tags' => ['template', 'http', 'api'],
            ],
        ];

        foreach ($templateWorkflows as $templateData) {
            $organization = $organizations->random();
            $user = $organization->owner;

            Workflow::create([
                'name' => $templateData['name'],
                'slug' => \Illuminate\Support\Str::slug($templateData['name']) . '-template-' . rand(1000, 9999),
                'description' => $templateData['description'],
                'organization_id' => $organization->id,
                'team_id' => null,
                'user_id' => $user->id,
                'workflow_data' => $templateData['workflow_data'],
                'settings' => [],
                'status' => 'draft',
                'is_active' => false,
                'is_template' => true,
                'tags' => $templateData['tags'],
            ]);
        }
    }
}
