<?php

namespace Database\Seeders;

use App\Models\Execution;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class ExecutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workflows = Workflow::all();
        $users = User::all();

        if ($workflows->isEmpty() || $users->isEmpty()) {
            return;
        }

        $this->createSampleExecutions($workflows, $users);
    }

    private function createSampleExecutions($workflows, $users): void
    {
        $sampleExecutions = [
            [
                'mode' => 'webhook',
                'status' => 'success',
                'input_data' => [
                    'method' => 'POST',
                    'url' => '/support/ticket',
                    'body' => [
                        'title' => 'Cannot access dashboard',
                        'description' => 'User is unable to log into their account',
                        'priority' => 'high',
                        'customer_email' => 'customer@example.com',
                    ],
                    'headers' => [
                        'content-type' => 'application/json',
                        'user-agent' => 'PostmanRuntime/7.29.2',
                    ],
                ],
                'output_data' => [
                    'ticket_id' => 12345,
                    'status' => 'created',
                    'assigned_to' => 'support_team',
                ],
                'duration' => rand(100, 500),
                'retry_count' => 0,
                'max_retries' => 3,
            ],
            [
                'mode' => 'api',
                'status' => 'success',
                'input_data' => [
                    'method' => 'GET',
                    'url' => '/api/sync',
                    'query' => ['sync_type' => 'full'],
                ],
                'output_data' => [
                    'records_processed' => 150,
                    'records_updated' => 45,
                    'records_created' => 105,
                    'sync_duration' => '2.3s',
                ],
                'duration' => rand(2000, 5000),
                'retry_count' => 0,
                'max_retries' => 3,
            ],
            [
                'mode' => 'manual',
                'status' => 'error',
                'input_data' => [
                    'user_id' => 123,
                    'action' => 'send_welcome_email',
                ],
                'output_data' => null,
                'error_message' => 'Failed to send email: SMTP connection timeout',
                'duration' => rand(5000, 10000),
                'retry_count' => 2,
                'max_retries' => 3,
            ],
            [
                'mode' => 'webhook',
                'status' => 'running',
                'input_data' => [
                    'method' => 'POST',
                    'url' => '/user/registered',
                    'body' => [
                        'user_id' => 456,
                        'user_email' => 'newuser@example.com',
                        'registration_date' => now()->toISOString(),
                    ],
                ],
                'output_data' => null,
                'duration' => rand(100, 1000),
                'retry_count' => 0,
                'max_retries' => 3,
            ],
            [
                'mode' => 'api',
                'status' => 'success',
                'input_data' => [
                    'method' => 'POST',
                    'url' => '/notification/send',
                    'body' => [
                        'to_email' => 'user@example.com',
                        'subject' => 'Welcome to our platform',
                        'message' => 'Thank you for joining us!',
                    ],
                ],
                'output_data' => [
                    'email_id' => 'msg_' . uniqid(),
                    'delivered' => true,
                    'delivery_time' => '0.8s',
                ],
                'duration' => rand(800, 2000),
                'retry_count' => 0,
                'max_retries' => 3,
            ],
        ];

        foreach ($workflows as $workflow) {
            // Create multiple executions for each workflow
            $executionCount = rand(3, 8);

            for ($i = 0; $i < $executionCount; $i++) {
                $executionData = $sampleExecutions[array_rand($sampleExecutions)];

                // Adjust timestamps to be in the past
                $createdAt = now()->subDays(rand(0, 30))->subHours(rand(0, 24));
                $startedAt = $createdAt->copy()->addSeconds(rand(1, 10));
                $finishedAt = $startedAt->copy()->addMilliseconds($executionData['duration']);

                Execution::create([
                    'workflow_id' => $workflow->id,
                    'organization_id' => $workflow->organization_id,
                    'user_id' => $users->random()->id,
                    'execution_id' => 'exec_' . uniqid() . '_' . $i,
                    'status' => $executionData['status'],
                    'mode' => $executionData['mode'],
                    'started_at' => $startedAt,
                    'finished_at' => in_array($executionData['status'], ['success', 'error', 'canceled']) ? $finishedAt : null,
                    'duration' => in_array($executionData['status'], ['success', 'error', 'canceled']) ? $executionData['duration'] : null,
                    'input_data' => $executionData['input_data'],
                    'output_data' => $executionData['output_data'],
                    'error_message' => $executionData['error_message'] ?? null,
                    'retry_count' => $executionData['retry_count'],
                    'max_retries' => $executionData['max_retries'],
                    'metadata' => [
                        'user_agent' => 'n8n-clone/1.0',
                        'ip_address' => '127.0.0.1',
                        'execution_version' => '1.0.0',
                    ],
                ]);

                // Update workflow execution count and last executed time
                $workflow->increment('execution_count');
                $workflow->update(['last_executed_at' => $createdAt]);
            }
        }
    }
}
