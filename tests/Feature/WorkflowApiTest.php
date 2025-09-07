<?php

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create and retrieve workflows', function () {
    $organization = \App\Models\Organization::factory()->create();
    $user = User::factory()->create();

    // Associate user with organization
    $organization->users()->attach($user->id, ['role' => 'admin']);
    $user->current_organization_id = $organization->id;
    $user->save();

    $workflowData = [
        'name' => 'Test Workflow',
        'description' => 'A test workflow for our n8n clone',
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'webhook1',
                    'type' => 'webhookTrigger',
                    'position' => ['x' => 100, 'y' => 100],
                    'properties' => [
                        'path' => '/test-webhook',
                        'method' => 'POST',
                    ],
                ],
                [
                    'id' => 'http1',
                    'type' => 'httpRequest',
                    'position' => ['x' => 300, 'y' => 100],
                    'properties' => [
                        'method' => 'GET',
                        'url' => 'https://httpbin.org/get',
                    ],
                ],
            ],
            'connections' => [
                [
                    'source' => 'webhook1',
                    'target' => 'http1',
                    'sourceOutput' => 'main',
                    'targetInput' => 'main',
                ],
            ],
        ],
        'status' => 'draft',
        'is_active' => false,
    ];

    // Create workflow
    $response = $this->actingAs($user)->postJson('/api/workflows', $workflowData);

    $response->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'message' => 'Workflow created successfully',
             ]);

    $workflowId = $response->json('data.id');

    // Retrieve workflow
    $response = $this->actingAs($user)->getJson("/api/workflows/{$workflowId}");

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'data' => [
                     'name' => 'Test Workflow',
                     'description' => 'A test workflow for our n8n clone',
                     'status' => 'draft',
                 ],
             ]);

    // Update workflow
    $updateData = [
        'name' => 'Updated Test Workflow',
        'status' => 'published',
        'is_active' => true,
    ];

    $response = $this->actingAs($user)->putJson("/api/workflows/{$workflowId}", $updateData);

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'message' => 'Workflow updated successfully',
                 'data' => [
                     'name' => 'Updated Test Workflow',
                     'status' => 'published',
                     'is_active' => true,
                 ],
             ]);
});

test('can execute workflow test mode', function () {
    $organization = \App\Models\Organization::factory()->create();
    $user = User::factory()->create();

    // Associate user with organization
    $organization->users()->attach($user->id, ['role' => 'admin']);
    $user->current_organization_id = $organization->id;
    $user->save();

    $workflow = Workflow::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'webhook1',
                    'type' => 'webhookTrigger',
                    'properties' => [
                        'path' => '/test-webhook',
                        'method' => 'POST',
                    ],
                ],
            ],
            'connections' => [],
        ],
        'is_active' => true,
    ]);

    $testData = [
        'message' => 'Hello from test',
        'timestamp' => now()->toISOString(),
    ];

    $response = $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/test-execute", [
        'data' => $testData,
    ]);

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
             ]);
});

test('can retrieve node manifest', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/nodes/manifest');

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
             ]);

    $manifest = $response->json('data');

    // Should contain our core nodes
    expect($manifest)->toBeArray();
    expect(count($manifest))->toBeGreaterThan(0);

    // Check for specific nodes
    $nodeIds = array_column($manifest, 'id');
    expect($nodeIds)->toContain('webhookTrigger');
    expect($nodeIds)->toContain('httpRequest');
    expect($nodeIds)->toContain('databaseQuery');
    expect($nodeIds)->toContain('email');
});

test('can validate node properties', function () {
    $user = User::factory()->create();

    $properties = [
        'method' => 'GET',
        'url' => 'https://httpbin.org/get',
    ];

    $response = $this->actingAs($user)->postJson('/api/nodes/httpRequest/validate-properties', [
        'properties' => $properties,
    ]);

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'data' => [
                     'valid' => true,
                     'node_id' => 'httpRequest',
                 ],
             ]);
});

test('can retrieve executions', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/executions');

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
             ]);
});

test('unauthenticated requests are rejected', function () {
    $workflowData = [
        'name' => 'Test Workflow',
        'description' => 'A test workflow',
        'workflow_data' => ['nodes' => [], 'connections' => []],
    ];

    $response = $this->postJson('/api/workflows', $workflowData);

    $response->assertStatus(401);
});

test('webhook endpoint accepts public requests', function () {
    $organization = \App\Models\Organization::factory()->create();
    $user = User::factory()->create();

    // Associate user with organization
    $organization->users()->attach($user->id, ['role' => 'admin']);
    $user->current_organization_id = $organization->id;
    $user->save();

    $workflow = Workflow::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'webhook1',
                    'type' => 'webhookTrigger',
                    'properties' => [
                        'path' => '/test-webhook',
                        'method' => 'POST',
                    ],
                ],
            ],
            'connections' => [],
        ],
        'is_active' => true,
    ]);

    // Test webhook endpoint (should work without authentication)
    $webhookData = [
        'message' => 'Hello from webhook',
        'user_id' => 123,
    ];

    $response = $this->postJson("/api/webhooks/{$workflow->id}", $webhookData);

    $response->assertStatus(200);

    // The webhook returns the workflow execution result, not a simple success message
    $responseData = $response->json();
    expect($responseData)->toBeArray();
    expect(count($responseData))->toBeGreaterThan(0);
});

test('can duplicate workflow', function () {
    $organization = \App\Models\Organization::factory()->create();
    $user = User::factory()->create();

    // Associate user with organization
    $organization->users()->attach($user->id, ['role' => 'admin']);
    $user->current_organization_id = $organization->id;
    $user->save();

    $originalWorkflow = Workflow::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'name' => 'Original Workflow',
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'webhook1',
                    'type' => 'webhookTrigger',
                    'properties' => ['method' => 'POST'],
                ],
            ],
            'connections' => [],
        ],
    ]);

    $response = $this->actingAs($user)->postJson("/api/workflows/{$originalWorkflow->id}/duplicate");

    $response->assertStatus(201)
             ->assertJson([
                 'success' => true,
                 'message' => 'Workflow duplicated successfully',
             ]);

    $duplicatedWorkflow = $response->json('data');

    expect($duplicatedWorkflow['name'])->toBe('Original Workflow (Copy)');
    expect($duplicatedWorkflow['id'])->not->toBe($originalWorkflow->id);
});

test('can export workflow', function () {
    $organization = \App\Models\Organization::factory()->create();
    $user = User::factory()->create();

    // Associate user with organization
    $organization->users()->attach($user->id, ['role' => 'admin']);
    $user->current_organization_id = $organization->id;
    $user->save();

    $workflow = Workflow::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'name' => 'Export Test Workflow',
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'webhook1',
                    'type' => 'webhookTrigger',
                    'properties' => ['method' => 'POST'],
                ],
            ],
            'connections' => [],
        ],
    ]);

    $response = $this->actingAs($user)->getJson("/api/workflows/{$workflow->id}/export");

    $response->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'data' => [
                     'name' => 'Export Test Workflow',
                     'version' => '1.0',
                 ],
             ]);

    $exportData = $response->json('data');
    expect($exportData)->toHaveKey('exported_at');
    expect($exportData['exported_at'])->toBeString();
});
