# 🧪 Testing Guide

Comprehensive testing documentation for the n8n clone workflow automation platform.

## 🎯 Testing Overview

The n8n clone implements a comprehensive testing strategy covering unit tests, feature tests, integration tests, and end-to-end tests to ensure high code quality and reliability.

## 📊 Test Statistics

- **Test Coverage**: 90%+ code coverage target
- **Test Types**: Unit, Feature, Integration, E2E
- **Test Framework**: PHPUnit + Pest
- **CI/CD**: Automated testing pipeline

## 🏗️ Test Structure

### Directory Structure

```
tests/
├── Feature/           # Feature tests (HTTP endpoints)
│   ├── AuthTest.php
│   ├── WorkflowTest.php
│   ├── ExecutionTest.php
│   └── NodeTest.php
├── Unit/             # Unit tests (individual classes)
│   ├── Models/
│   ├── Services/
│   └── Utilities/
├── Pest.php         # Pest test configuration
└── TestCase.php     # Base test case
```

### Test Naming Convention

```php
// Feature tests
class WorkflowApiTest extends TestCase
{
    /** @test */
    public function can_create_and_retrieve_workflows() {}

    /** @test */
    public function can_execute_workflow_test_mode() {}

    /** @test */
    public function webhook_endpoint_accepts_public_requests() {}
}

// Unit tests
class WorkflowEngineTest extends TestCase
{
    /** @test */
    public function executes_workflow_with_valid_data() {}

    /** @test */
    public function handles_node_execution_errors_gracefully() {}
}
```

## 🧪 Feature Tests (API Testing)

### Authentication Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user',
                        'token',
                        'token_type'
                    ]
                ]);

        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    /** @test */
    public function authenticated_user_can_access_protected_routes()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ]
                ]);
    }
}
```

### Workflow API Tests

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create([
            'owner_id' => $this->user->id,
        ]);

        $this->user->update([
            'current_organization_id' => $this->organization->id,
        ]);

        $this->actingAs($this->user, 'sanctum');
    }

    /** @test */
    public function can_create_and_retrieve_workflows()
    {
        $workflowData = [
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
            'organization_id' => $this->organization->id,
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
                ],
                'connections' => [],
            ],
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/workflows', $workflowData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'Test Workflow',
                        'status' => 'draft',
                    ]
                ]);

        $workflow = Workflow::first();
        $this->assertEquals('Test Workflow', $workflow->name);
        $this->assertEquals($this->organization->id, $workflow->organization_id);
    }

    /** @test */
    public function can_execute_workflow_test_mode()
    {
        $workflow = Workflow::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'workflow_data' => [
                'nodes' => [
                    [
                        'id' => 'webhook1',
                        'type' => 'webhookTrigger',
                        'properties' => [
                            'path' => '/test',
                            'method' => 'POST',
                        ],
                    ],
                    [
                        'id' => 'http1',
                        'type' => 'httpRequest',
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
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/test-execute", [
            'data' => ['test' => 'data']
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        // Response data from HTTP request
                    ]
                ]);
    }

    /** @test */
    public function webhook_endpoint_accepts_public_requests()
    {
        $workflow = Workflow::factory()->create([
            'organization_id' => $this->organization->id,
            'workflow_data' => [
                'nodes' => [
                    [
                        'id' => 'webhook1',
                        'type' => 'webhookTrigger',
                        'properties' => [
                            'path' => '/public-test',
                            'method' => 'POST',
                        ],
                    ],
                ],
                'connections' => [],
            ],
        ]);

        // Test without authentication (webhooks are public)
        $response = $this->postJson("/api/webhooks/{$workflow->id}", [
            'message' => 'Test webhook data',
            'timestamp' => now()->toISOString(),
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);
    }

    /** @test */
    public function can_duplicate_workflow()
    {
        $originalWorkflow = Workflow::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'name' => 'Original Workflow',
        ]);

        $response = $this->postJson("/api/workflows/{$originalWorkflow->id}/duplicate");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'Original Workflow (Copy)',
                    ]
                ]);

        $this->assertEquals(2, Workflow::count());
        $duplicate = Workflow::where('name', 'Original Workflow (Copy)')->first();
        $this->assertNotNull($duplicate);
    }

    /** @test */
    public function can_export_workflow()
    {
        $workflow = Workflow::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'name' => 'Export Test',
            'workflow_data' => ['nodes' => [], 'connections' => []],
        ]);

        $response = $this->getJson("/api/workflows/{$workflow->id}/export");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'Export Test',
                        'workflow_data' => ['nodes' => [], 'connections' => []],
                        'exported_at' => now()->toISOString(),
                    ]
                ]);
    }

    /** @test */
    public function unauthenticated_requests_are_rejected()
    {
        $response = $this->getJson('/api/workflows');

        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.'
                ]);
    }
}
```

## 🔧 Unit Tests

### Model Tests

```php
<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Organization;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_belong_to_organization()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $user->organizations()->attach($organization, [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->assertTrue($user->organizations->contains($organization));
        $this->assertEquals('member', $user->organizations->first()->pivot->role);
    }

    /** @test */
    public function user_can_have_current_organization()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $user->update(['current_organization_id' => $organization->id]);

        $this->assertEquals($organization->id, $user->current_organization_id);
        $this->assertEquals($organization->id, $user->currentOrganization->id);
    }

    /** @test */
    public function user_can_create_workflows()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $workflow = Workflow::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ]);

        $this->assertTrue($user->workflows->contains($workflow));
        $this->assertEquals($organization->id, $workflow->organization_id);
    }
}
```

### Service Tests

```php
<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\Execution;
use App\Workflow\Engine\WorkflowExecutionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowExecutionEngineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowExecutionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(WorkflowExecutionEngine::class);
    }

    /** @test */
    public function executes_workflow_with_valid_data()
    {
        $user = User::factory()->create();
        $workflow = Workflow::factory()->create([
            'user_id' => $user->id,
            'workflow_data' => [
                'nodes' => [
                    [
                        'id' => 'webhook1',
                        'type' => 'webhookTrigger',
                        'properties' => [
                            'path' => '/test',
                            'method' => 'POST',
                        ],
                    ],
                ],
                'connections' => [],
            ],
        ]);

        $triggerData = [
            'method' => 'POST',
            'body' => ['test' => 'data'],
            'headers' => ['content-type' => 'application/json'],
        ];

        $result = $this->engine->executeWorkflowSync($workflow, $triggerData);

        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getOutputData());

        // Check that execution was created
        $execution = Execution::where('workflow_id', $workflow->id)->first();
        $this->assertNotNull($execution);
        $this->assertEquals('success', $execution->status);
    }

    /** @test */
    public function handles_node_execution_errors_gracefully()
    {
        $user = User::factory()->create();
        $workflow = Workflow::factory()->create([
            'user_id' => $user->id,
            'workflow_data' => [
                'nodes' => [
                    [
                        'id' => 'http1',
                        'type' => 'httpRequest',
                        'properties' => [
                            'method' => 'GET',
                            'url' => 'https://invalid-domain-that-does-not-exist.com',
                            'timeout' => 1,
                        ],
                    ],
                ],
                'connections' => [],
            ],
        ]);

        $result = $this->engine->executeWorkflowSync($workflow);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('error', $result->getErrorMessage());

        // Check that execution was recorded with error
        $execution = Execution::where('workflow_id', $workflow->id)->first();
        $this->assertNotNull($execution);
        $this->assertEquals('error', $execution->status);
        $this->assertNotNull($execution->error_message);
    }

    /** @test */
    public function validates_workflow_structure()
    {
        $user = User::factory()->create();
        $workflow = Workflow::factory()->create([
            'user_id' => $user->id,
            'workflow_data' => [
                'nodes' => [], // Empty nodes - should fail validation
                'connections' => [],
            ],
        ]);

        $validation = $this->engine->validateWorkflow($workflow);

        $this->assertFalse($validation['valid']);
        $this->assertContains('must contain at least one node', $validation['errors']);
    }

    /** @test */
    public function detects_circular_dependencies()
    {
        $user = User::factory()->create();
        $workflow = Workflow::factory()->create([
            'user_id' => $user->id,
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'node1', 'type' => 'webhookTrigger'],
                    ['id' => 'node2', 'type' => 'httpRequest'],
                    ['id' => 'node3', 'type' => 'email'],
                ],
                'connections' => [
                    ['source' => 'node1', 'target' => 'node2'],
                    ['source' => 'node2', 'target' => 'node3'],
                    ['source' => 'node3', 'target' => 'node1'], // Creates cycle
                ],
            ],
        ]);

        $validation = $this->engine->validateWorkflow($workflow);

        $this->assertFalse($validation['valid']);
        $this->assertContains('circular dependencies', $validation['errors']);
    }
}
```

### Node Tests

```php
<?php

namespace Tests\Unit\Nodes;

use App\Nodes\Core\HttpRequestNode;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Tests\TestCase;

class HttpRequestNodeTest extends TestCase
{
    private HttpRequestNode $node;

    protected function setUp(): void
    {
        parent::setUp();
        $this->node = new HttpRequestNode();
    }

    /** @test */
    public function node_has_correct_metadata()
    {
        $this->assertEquals('httpRequest', $this->node->getId());
        $this->assertEquals('HTTP Request', $this->node->getName());
        $this->assertEquals('action', $this->node->getCategory());
        $this->assertEquals('1.0.0', $this->node->getVersion());
    }

    /** @test */
    public function node_has_required_properties()
    {
        $properties = $this->node->getProperties();

        $this->assertArrayHasKey('method', $properties);
        $this->assertArrayHasKey('url', $properties);
        $this->assertArrayHasKey('timeout', $properties);
        $this->assertEquals('GET', $properties['method']['default']);
        $this->assertTrue($properties['url']['required']);
    }

    /** @test */
    public function node_has_correct_inputs_and_outputs()
    {
        $inputs = $this->node->getInputs();
        $outputs = $this->node->getOutputs();

        $this->assertArrayHasKey('main', $inputs);
        $this->assertArrayHasKey('main', $outputs);
        $this->assertArrayHasKey('error', $outputs);
    }

    /** @test */
    public function validates_properties_correctly()
    {
        // Valid properties
        $validProperties = [
            'method' => 'GET',
            'url' => 'https://api.example.com/data',
            'timeout' => 30,
        ];

        $this->assertTrue($this->node->validateProperties($validProperties));

        // Invalid properties
        $invalidProperties = [
            'method' => 'INVALID_METHOD',
            'url' => '', // Empty URL
        ];

        $this->assertFalse($this->node->validateProperties($invalidProperties));
    }

    /** @test */
    public function executes_successfully_with_mock_http_client()
    {
        // Mock HTTP client response
        Http::fake([
            'api.example.com/*' => Http::response([
                'success' => true,
                'data' => ['test' => 'response']
            ], 200, ['content-type' => 'application/json'])
        ]);

        $context = $this->createMockContext([
            'method' => 'GET',
            'url' => 'https://api.example.com/test',
        ]);

        $result = $this->node->execute($context);

        $this->assertTrue($result->isSuccess());
        $outputData = $result->getOutputData();

        $this->assertEquals(200, $outputData[0]['statusCode']);
        $this->assertEquals('OK', $outputData[0]['statusText']);
        $this->assertArrayHasKey('body', $outputData[0]);
    }

    /** @test */
    public function handles_http_errors_gracefully()
    {
        Http::fake([
            'api.example.com/*' => Http::response('Not Found', 404)
        ]);

        $context = $this->createMockContext([
            'method' => 'GET',
            'url' => 'https://api.example.com/not-found',
        ]);

        $result = $this->node->execute($context);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(\Exception::class, $result->getException());
    }

    private function createMockContext(array $properties): NodeExecutionContext
    {
        $workflow = \Mockery::mock(\App\Models\Workflow::class);
        $execution = \Mockery::mock(\App\Models\Execution::class);
        $user = \Mockery::mock(\App\Models\User::class);

        return new NodeExecutionContext(
            $workflow,
            $execution,
            $user,
            'test_node_id',
            ['type' => 'httpRequest', 'properties' => $properties],
            [],
            $properties
        );
    }
}
```

## 🧰 Testing Utilities

### Test Traits

```php
<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait CreatesTestData
{
    use RefreshDatabase;

    protected User $testUser;
    protected Organization $testOrganization;

    protected function setUpTestData(): void
    {
        $this->testUser = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->testOrganization = Organization::factory()->create([
            'owner_id' => $this->testUser->id,
            'name' => 'Test Organization',
        ]);

        $this->testUser->update([
            'current_organization_id' => $this->testOrganization->id,
        ]);
    }

    protected function actingAsTestUser(): self
    {
        return $this->actingAs($this->testUser, 'sanctum');
    }

    protected function createTestWorkflow(array $overrides = []): \App\Models\Workflow
    {
        return \App\Models\Workflow::factory()->create(array_merge([
            'organization_id' => $this->testOrganization->id,
            'user_id' => $this->testUser->id,
        ], $overrides));
    }
}
```

### API Test Helpers

```php
<?php

namespace Tests\Helpers;

use Illuminate\Testing\TestResponse;

class ApiTestHelper
{
    public static function assertSuccessfulApiResponse(TestResponse $response, array $expectedStructure = []): void
    {
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        if (!empty($expectedStructure)) {
            $response->assertJsonStructure([
                'success',
                'data' => $expectedStructure,
            ]);
        }
    }

    public static function assertApiError(TestResponse $response, int $statusCode = 400, string $message = null): void
    {
        $response->assertStatus($statusCode)
                ->assertJson([
                    'success' => false,
                ]);

        if ($message) {
            $response->assertJson([
                'message' => $message,
            ]);
        }
    }

    public static function assertPaginatedResponse(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'success',
            'data',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'from',
                'to',
            ]
        ]);
    }

    public static function authenticateUser(\App\Models\User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;
        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
```

## 🔄 Database Testing

### Model Factories

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $this->faker->randomElement(['user', 'admin']),
            'email_verified_at' => now(),
        ];
    }

    public function admin(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => 'admin',
            ];
        });
    }

    public function unverified(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->paragraph(),
            'organization_id' => \App\Models\Organization::factory(),
            'user_id' => \App\Models\User::factory(),
            'workflow_data' => [
                'nodes' => [
                    [
                        'id' => 'webhook_' . $this->faker->uuid(),
                        'type' => 'webhookTrigger',
                        'position' => [
                            'x' => $this->faker->numberBetween(0, 800),
                            'y' => $this->faker->numberBetween(0, 600),
                        ],
                        'properties' => [
                            'path' => '/' . $this->faker->slug(),
                            'method' => 'POST',
                        ],
                    ],
                ],
                'connections' => [],
            ],
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'tags' => $this->faker->randomElements(
                ['automation', 'api', 'webhook', 'email', 'database', 'notification'],
                $this->faker->numberBetween(1, 3)
            ),
        ];
    }

    public function published(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'published',
                'is_active' => true,
            ];
        });
    }

    public function withExecutions(int $count = 5): self
    {
        return $this->afterCreating(function (Workflow $workflow) use ($count) {
            \App\Models\Execution::factory()->count($count)->create([
                'workflow_id' => $workflow->id,
                'organization_id' => $workflow->organization_id,
                'user_id' => $workflow->user_id,
            ]);
        });
    }
}
```

## 🚀 Testing Commands

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/WorkflowApiTest.php

# Run specific test method
php artisan test --filter=can_create_and_retrieve_workflows

# Run tests with coverage
php artisan test --coverage

# Run tests for specific directory
php artisan test tests/Unit/

# Run tests in parallel
php artisan test --parallel
```

### Test Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         verbose="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory>./app/Console</directory>
        </exclude>
        <report>
            <html outputDirectory="tests/coverage/html"/>
            <text outputFile="tests/coverage/coverage.txt"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

### Pest Configuration

```php
<!-- tests/Pest.php -->
<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// Global test functions
function authenticateUser($user = null) {
    $user = $user ?? \App\Models\User::factory()->create();
    return [
        'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken
    ];
}

function createWorkflow($overrides = []) {
    return \App\Models\Workflow::factory()->create($overrides);
}

function assertSuccessfulResponse($response) {
    expect($response->json('success'))->toBeTrue();
    return $response;
}

function assertErrorResponse($response, $status = 400) {
    expect($response->status())->toBe($status);
    expect($response->json('success'))->toBeFalse();
    return $response;
}
```

## 📊 Test Coverage

### Coverage Goals

- **Models**: 95%+ coverage
- **Services**: 90%+ coverage
- **Controllers**: 85%+ coverage
- **Nodes**: 90%+ coverage
- **Overall**: 85%+ coverage

### Coverage Report

```bash
# Generate HTML coverage report
php artisan test --coverage-html=tests/coverage

# Generate text coverage report
php artisan test --coverage-text

# Minimum coverage threshold
php artisan test --coverage --min=85
```

### Coverage Configuration

```xml
<!-- phpunit.xml coverage configuration -->
<coverage>
    <include>
        <directory suffix=".php">./app</directory>
    </include>
    <exclude>
        <file>./app/Console/Kernel.php</file>
        <directory>./app/Exceptions</directory>
    </exclude>
    <report>
        <html outputDirectory="./tests/coverage/html" lowUpperBound="35" highLowerBound="70"/>
        <text outputFile="./tests/coverage/coverage.txt" showOnlySummary="true"/>
        <clover outputFile="./tests/coverage/clover.xml"/>
    </report>
</coverage>
```

## 🔄 Continuous Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: n8n_clone_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:7.0-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: pdo, pdo_mysql, redis
        coverage: xdebug

    - name: Cache Composer dependencies
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-composer-

    - name: Install PHP dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Install Node.js dependencies
      run: npm ci

    - name: Copy environment file
      run: cp .env.ci .env

    - name: Generate application key
      run: php artisan key:generate

    - name: Run migrations
      run: php artisan migrate --force

    - name: Run tests
      run: php artisan test --coverage --min=85

    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./tests/coverage/clover.xml
```

## 🐛 Debugging Tests

### Common Debugging Techniques

```php
/** @test */
public function debug_workflow_execution()
{
    // Enable query logging
    \DB::enableQueryLog();

    $workflow = $this->createTestWorkflow();
    $result = $this->engine->executeWorkflowSync($workflow);

    // Log all executed queries
    $queries = \DB::getQueryLog();
    \Log::info('Executed queries', $queries);

    // Debug execution result
    dump($result->getOutputData());
    dump($result->getErrorMessage());

    $this->assertTrue($result->isSuccess());
}
```

### Test Data Inspection

```php
/** @test */
public function inspect_test_data()
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    // Inspect created data
    dump($user->toArray());
    dump($organization->toArray());

    // Check relationships
    $this->assertEquals($organization->id, $user->current_organization_id);
}
```

### API Response Debugging

```php
/** @test */
public function debug_api_response()
{
    $response = $this->actingAsTestUser()
                    ->postJson('/api/workflows', [
                        'name' => 'Test Workflow',
                        'organization_id' => $this->testOrganization->id,
                    ]);

    // Debug response
    if ($response->status() !== 201) {
        dump($response->json());
        dump($response->getContent());
    }

    $response->assertStatus(201);
}
```

## 📈 Performance Testing

### Load Testing

```php
/** @test */
public function handles_multiple_concurrent_executions()
{
    $workflow = $this->createTestWorkflow();
    $concurrentRequests = 10;

    $responses = collect();
    for ($i = 0; $i < $concurrentRequests; $i++) {
        $responses->push(
            $this->postJson("/api/workflows/{$workflow->id}/execute", [
                'data' => ['request_id' => $i]
            ])
        );
    }

    // All responses should be successful
    $responses->each(function ($response) {
        $response->assertStatus(200);
    });

    // Check that executions were created
    $this->assertEquals($concurrentRequests, Execution::count());
}
```

### Memory Testing

```php
/** @test */
public function does_not_have_memory_leaks()
{
    $initialMemory = memory_get_usage();

    for ($i = 0; $i < 100; $i++) {
        $workflow = $this->createTestWorkflow();
        $result = $this->engine->executeWorkflowSync($workflow);

        // Force garbage collection
        gc_collect_cycles();
    }

    $finalMemory = memory_get_usage();
    $memoryIncrease = $finalMemory - $initialMemory;

    // Memory increase should be reasonable (< 10MB)
    $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
}
```

---

**🧪 Comprehensive testing ensures the reliability, performance, and maintainability of your n8n clone workflow automation platform.**
