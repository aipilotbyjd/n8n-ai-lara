# 🤝 Contributing Guide

Welcome! We're excited that you're interested in contributing to the n8n clone workflow automation platform. This guide will help you get started with development, understand our processes, and make meaningful contributions.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Development Workflow](#development-workflow)
- [Contributing Types](#contributing-types)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Documentation](#documentation)
- [Pull Request Process](#pull-request-process)
- [Community](#community)

## 🤝 Code of Conduct

This project follows a code of conduct to ensure a welcoming environment for all contributors. By participating, you agree to:

- **Be Respectful**: Treat all contributors with respect and kindness
- **Be Inclusive**: Welcome contributors from all backgrounds and skill levels
- **Be Collaborative**: Work together to solve problems and improve the project
- **Be Patient**: Understand that contributors have different time zones and commitments
- **Be Constructive**: Provide helpful feedback and focus on solutions

## 🚀 Getting Started

### Prerequisites

Before contributing, ensure you have:
- PHP 8.2+ with Composer
- Node.js 18+ with NPM
- PostgreSQL or MySQL
- Redis (for caching and queues)
- Git

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
```bash
git clone https://github.com/your-username/n8n-ai-lara.git
cd n8n-ai-lara
```

3. Set up upstream remote:
```bash
git remote add upstream https://github.com/original-repo/n8n-ai-lara.git
```

### Development Setup

1. Install PHP dependencies:
```bash
composer install
```

2. Install Node.js dependencies:
```bash
npm install
```

3. Set up environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Set up database:
```bash
# Create database
php artisan migrate

# Seed with demo data (optional)
php artisan n8n:seed-demo
```

5. Build frontend assets:
```bash
npm run build
```

6. Start development server:
```bash
php artisan serve
```

## 🔄 Development Workflow

### 1. Choose an Issue

- Check [GitHub Issues](https://github.com/your-repo/issues) for open tasks
- Look for issues labeled `good first issue` or `help wanted`
- Comment on the issue to indicate you're working on it

### 2. Create a Branch

```bash
# Create and switch to new branch
git checkout -b feature/your-feature-name
# or
git checkout -b fix/issue-number-description
# or
git checkout -b docs/update-contributing-guide
```

### 3. Make Changes

Follow our coding standards and commit conventions:
```bash
# Make your changes
# Test your changes
# Add tests if needed

# Stage your changes
git add .

# Commit with conventional format
git commit -m "feat: add new webhook trigger node

- Add webhook trigger node implementation
- Add webhook validation
- Add comprehensive tests
- Update documentation

Closes #123"
```

### 4. Push and Create PR

```bash
# Push your branch
git push origin feature/your-feature-name

# Create Pull Request on GitHub
```

## 📝 Contributing Types

### 🐛 Bug Fixes

**Process:**
1. Find or create an issue describing the bug
2. Write a failing test that reproduces the bug
3. Fix the bug
4. Ensure all tests pass
5. Update documentation if needed

**Example:**
```php
// Test that reproduces the bug
public function test_webhook_trigger_handles_empty_payload()
{
    $workflow = Workflow::factory()->create([
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'webhook1',
                    'type' => 'webhookTrigger',
                    'properties' => ['path' => '/test']
                ]
            ]
        ]
    ]);

    // This should not throw an exception
    $response = $this->postJson("/api/webhooks/{$workflow->id}", []);

    $response->assertStatus(200);
}
```

### ✨ New Features

**Process:**
1. Create an issue describing the feature
2. Discuss the implementation approach
3. Write tests for the new functionality
4. Implement the feature
5. Update documentation
6. Add examples and usage instructions

**Example Feature: Custom Email Node**
```php
<?php
namespace App\Nodes\Custom;

class CustomEmailNode implements NodeInterface
{
    public function getId(): string
    {
        return 'customEmail';
    }

    public function getName(): string
    {
        return 'Custom Email Service';
    }

    public function getProperties(): array
    {
        return [
            'template' => [
                'type' => 'select',
                'options' => ['welcome', 'notification', 'alert'],
                'required' => true,
            ],
            'recipient' => [
                'type' => 'string',
                'required' => true,
            ],
            // ... more properties
        ];
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        // Implementation
    }
}
```

### 📚 Documentation

**Process:**
1. Identify documentation that needs updating
2. Follow the existing documentation structure
3. Use clear, concise language
4. Include practical examples
5. Test all code examples

**Documentation Structure:**
```
docs/
├── README.md                 # Main documentation overview
├── installation-setup.md     # Installation instructions
├── api-documentation.md      # API reference
├── architecture-overview.md  # System architecture
├── database-schema.md        # Database structure
├── node-system.md           # Node development
├── workflow-engine.md       # Execution engine
├── authentication.md        # Auth & security
├── testing-guide.md         # Testing documentation
├── deployment-guide.md      # Production deployment
├── troubleshooting.md       # Common issues
├── faq.md                   # Frequently asked questions
└── contributing.md          # This file
```

### 🧪 Tests

**Process:**
1. Identify untested code or edge cases
2. Write comprehensive tests
3. Follow testing best practices
4. Ensure good test coverage
5. Test both success and failure scenarios

## 💻 Coding Standards

### PHP Standards

We follow PSR-12 coding standards:

```php
<?php
declare(strict_types=1);

namespace App\Example;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Example model class
 *
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Example extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the example.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Example method with proper documentation
     *
     * @param string $input
     * @return string
     * @throws \InvalidArgumentException
     */
    public function processInput(string $input): string
    {
        if (empty($input)) {
            throw new \InvalidArgumentException('Input cannot be empty');
        }

        return strtoupper($input);
    }
}
```

### JavaScript/Vue Standards

```javascript
// Use ES6+ features
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'WorkflowCanvas',
  props: {
    workflow: {
      type: Object,
      required: true,
      validator: (value) => value && value.id
    }
  },
  setup(props) {
    const nodes = ref([])
    const connections = ref([])

    const totalNodes = computed(() => nodes.value.length)

    const addNode = async (nodeData) => {
      try {
        const response = await axios.post('/api/workflows/nodes', nodeData)
        nodes.value.push(response.data)
      } catch (error) {
        console.error('Failed to add node:', error)
        throw error
      }
    }

    const validateWorkflow = () => {
      // Validation logic
      return nodes.value.length > 0
    }

    onMounted(() => {
      // Component initialization
    })

    return {
      nodes,
      connections,
      totalNodes,
      addNode,
      validateWorkflow
    }
  }
}
```

### Commit Message Convention

We use conventional commits:

```bash
# Format: type(scope): description

# Examples:
feat(auth): add two-factor authentication
fix(webhooks): handle empty payload gracefully
docs(api): update workflow execution examples
test(nodes): add tests for database query node
refactor(engine): optimize workflow execution performance
chore(deps): update Laravel to version 12

# Breaking changes
feat!: change workflow API response format

# With scope
feat(webhooks): add support for custom headers
fix(database): resolve connection timeout issue
```

### Naming Conventions

#### PHP
- **Classes**: PascalCase (`WorkflowEngine`, `NodeRegistry`)
- **Methods**: camelCase (`executeWorkflow`, `getNodeById`)
- **Properties**: camelCase (`$workflowData`, `$executionTime`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_RETRY_ATTEMPTS`)
- **Files**: PascalCase matching class name

#### JavaScript
- **Variables**: camelCase (`workflowData`, `nodeList`)
- **Functions**: camelCase (`handleSubmit`, `validateForm`)
- **Components**: PascalCase (`WorkflowCanvas`, `NodeEditor`)
- **Constants**: UPPER_SNAKE_CASE (`API_BASE_URL`)

#### Database
- **Tables**: snake_case (`user_permissions`, `workflow_executions`)
- **Columns**: snake_case (`created_at`, `updated_at`, `is_active`)
- **Indexes**: `idx_table_column` or `idx_table_columns`

## 🧪 Testing Guidelines

### Test Coverage Goals

- **Models**: 95%+ coverage
- **Services**: 90%+ coverage
- **Controllers**: 85%+ coverage
- **Nodes**: 90%+ coverage
- **Overall**: 85%+ coverage

### Writing Tests

#### Unit Tests

```php
<?php

namespace Tests\Unit\Services;

use App\Services\WorkflowExecutionService;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function executes_workflow_with_valid_data()
    {
        // Arrange
        $workflow = Workflow::factory()->create();
        $service = new WorkflowExecutionService();

        // Act
        $result = $service->executeWorkflow($workflow->id, ['test' => 'data']);

        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('execution_id', $result->getData());
    }

    /** @test */
    public function throws_exception_for_invalid_workflow_id()
    {
        // Arrange
        $service = new WorkflowExecutionService();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow not found');

        // Act
        $service->executeWorkflow(999, []);
    }

    /** @test */
    public function handles_execution_timeout_gracefully()
    {
        // Arrange
        $workflow = Workflow::factory()->create([
            'settings' => ['timeout' => 1] // 1 second timeout
        ]);
        $service = new WorkflowExecutionService();

        // Mock a slow operation
        // ...

        // Act & Assert
        $this->expectException(\TimeoutException::class);
        $service->executeWorkflow($workflow->id, []);
    }
}
```

#### Feature Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    /** @test */
    public function user_can_create_workflow()
    {
        $workflowData = [
            'name' => 'Test Workflow',
            'description' => 'A test workflow description',
            'organization_id' => $this->user->current_organization_id,
            'workflow_data' => [
                'nodes' => [
                    [
                        'id' => 'start',
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
        ];

        $response = $this->postJson('/api/workflows', $workflowData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'slug',
                        'status',
                        'created_at',
                    ],
                ]);

        $this->assertDatabaseHas('workflows', [
            'name' => 'Test Workflow',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_can_update_own_workflow()
    {
        $workflow = Workflow::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->user->current_organization_id,
        ]);

        $updateData = [
            'name' => 'Updated Workflow Name',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/workflows/{$workflow->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'Updated Workflow Name',
                        'description' => 'Updated description',
                    ],
                ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'name' => 'Updated Workflow Name',
        ]);
    }

    /** @test */
    public function user_cannot_update_others_workflow()
    {
        $otherUser = User::factory()->create();
        $workflow = Workflow::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->putJson("/api/workflows/{$workflow->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Access denied to this resource',
                ]);
    }
}
```

### Test Data Management

```php
<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\Organization;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait CreatesTestData
{
    use RefreshDatabase;

    protected User $testUser;
    protected Organization $testOrganization;
    protected Workflow $testWorkflow;

    protected function setUpTestData(): void
    {
        $this->testUser = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->testOrganization = Organization::factory()->create([
            'owner_id' => $this->testUser->id,
        ]);

        $this->testUser->update([
            'current_organization_id' => $this->testOrganization->id,
        ]);

        $this->testWorkflow = Workflow::factory()->create([
            'user_id' => $this->testUser->id,
            'organization_id' => $this->testOrganization->id,
        ]);
    }

    protected function authenticateTestUser(): string
    {
        return $this->testUser->createToken('test-token')->plainTextToken;
    }

    protected function createTestWorkflow(array $overrides = []): Workflow
    {
        return Workflow::factory()->create(array_merge([
            'user_id' => $this->testUser->id,
            'organization_id' => $this->testOrganization->id,
        ], $overrides));
    }
}
```

## 📚 Documentation

### Documentation Standards

1. **Clear Structure**: Use consistent headings and sections
2. **Practical Examples**: Include working code examples
3. **Step-by-Step Instructions**: Provide detailed procedures
4. **Troubleshooting**: Include common issues and solutions
5. **Cross-References**: Link related documentation

### API Documentation

```php
/**
 * Execute a workflow
 *
 * Executes a workflow with the provided data and returns the results.
 *
 * @group Workflows
 * @subgroup Execution
 *
 * @urlParam workflowId integer required The workflow ID
 *
 * @bodyParam data object The input data for the workflow
 * @bodyParam async boolean Execute asynchronously (default: true)
 *
 * @response 200 {
 *   "success": true,
 *   "data": {
 *     "execution_id": "exec_123",
 *     "result": {...}
 *   }
 * }
 *
 * @response 404 {
 *   "success": false,
 *   "message": "Workflow not found"
 * }
 */
public function execute(Request $request, Workflow $workflow)
{
    // Implementation
}
```

## 🔄 Pull Request Process

### 1. Before Submitting

- [ ] Tests pass locally (`php artisan test`)
- [ ] Code follows style guidelines (`./vendor/bin/php-cs-fixer fix`)
- [ ] Documentation updated
- [ ] Commit messages follow conventional format
- [ ] Branch is up to date with main

### 2. Creating the PR

1. **Title**: Use conventional commit format
   ```
   feat: add webhook retry mechanism
   fix: resolve database connection timeout
   docs: update API documentation
   ```

2. **Description**: Include detailed description
   ```markdown
   ## Description
   Add retry mechanism for webhook deliveries that fail due to network issues.

   ## Changes Made
   - Added retry configuration to webhook settings
   - Implemented exponential backoff algorithm
   - Added retry tracking and logging

   ## Testing
   - Added unit tests for retry logic
   - Added integration tests for webhook delivery
   - All existing tests pass

   ## Screenshots
   [If UI changes]

   ## Checklist
   - [x] Tests written
   - [x] Documentation updated
   - [x] Breaking changes noted
   ```

3. **Labels**: Add appropriate labels
   - `bug` - Bug fixes
   - `enhancement` - New features
   - `documentation` - Documentation changes
   - `breaking-change` - Breaking changes
   - `work-in-progress` - Not ready for review

### 3. Review Process

1. **Automated Checks**: CI/CD pipeline runs
   - Code style checks
   - Unit and feature tests
   - Security scans
   - Performance tests

2. **Code Review**: Maintainers review
   - Code quality and standards
   - Test coverage
   - Documentation
   - Security implications

3. **Approval**: At least one maintainer approval required

4. **Merge**: Squash and merge with conventional commit message

### 4. After Merge

1. **Delete Branch**: Delete the feature branch
2. **Close Issues**: Link PR to resolved issues
3. **Update Documentation**: Update any external documentation
4. **Release Notes**: Add to changelog if significant

## 🌍 Community

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: General questions and discussions
- **Discord**: Real-time community chat
- **Twitter**: Announcements and updates

### Getting Help

1. **Check Documentation**: Search existing docs first
2. **Search Issues**: Look for similar reported issues
3. **Create Issue**: If no existing issue, create a new one
4. **Community Support**: Ask in Discord or GitHub Discussions
5. **Professional Support**: Enterprise support contracts

### Recognition

Contributors are recognized through:
- **Contributors List**: Added to repository contributors
- **Release Notes**: Mentioned in release notes
- **Hall of Fame**: Featured contributors
- **Swag**: Occasional contributor rewards

## 🎯 Development Guidelines

### Code Review Checklist

**For Reviewers:**
- [ ] Code follows project standards
- [ ] Tests are comprehensive and passing
- [ ] Documentation is updated
- [ ] Security implications considered
- [ ] Performance impact evaluated
- [ ] Breaking changes properly documented

**For Contributors:**
- [ ] Self-review completed
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] Breaking changes documented
- [ ] Commit messages conventional
- [ ] PR description detailed

### Performance Considerations

- [ ] Database queries optimized
- [ ] Memory usage monitored
- [ ] Caching implemented where appropriate
- [ ] Asynchronous processing used for long operations
- [ ] Resource limits considered

### Security Checklist

- [ ] Input validation implemented
- [ ] Authentication/authorization checked
- [ ] Sensitive data encrypted
- [ ] SQL injection prevention
- [ ] XSS/CSRF protection
- [ ] Error messages don't leak sensitive information

---

**🤝 Thank you for contributing to the n8n clone! Your contributions help make workflow automation accessible to everyone.**

*Happy coding! 🚀*
