# 🚀 Quick Start Guide

Get your n8n clone up and running in 5 minutes! This guide will walk you through the essential steps to create your first workflow.

## ⚡ Prerequisites (2 minutes)

### System Requirements
- **PHP 8.2+** with Composer
- **Node.js 18+** with NPM
- **SQLite** (for development) or **MySQL/PostgreSQL** (for production)

### Quick Setup
```bash
# 1. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Install Node.js dependencies
npm install --production

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Setup database (SQLite for quick start)
touch database/database.sqlite
```

## 🏃‍♂️ Start the Application (1 minute)

```bash
# Start the Laravel server
php artisan serve

# In another terminal, start the frontend (if applicable)
npm run dev
```

Your application is now running at: **http://localhost:8000**

## 🔐 Create Your First User (30 seconds)

```bash
# Create an admin user
php artisan tinker

>>> User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
    'role' => 'admin'
]);

>>> exit
```

## 📊 Seed Demo Data (1 minute)

```bash
# Seed the database with demo data
php artisan n8n:seed-demo

# Or for fresh database
php artisan n8n:seed-demo --fresh
```

This creates:
- ✅ 5 sample users
- ✅ 2 organizations
- ✅ 4 teams
- ✅ 6 sample workflows
- ✅ 50+ workflow executions
- ✅ 8 API credentials

## 🎯 Your First Workflow

### 1. Create a Simple Webhook Workflow

```bash
# Using the API
curl -X POST "http://localhost:8000/api/workflows" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My First Workflow",
    "description": "A simple webhook workflow",
    "organization_id": 1,
    "workflow_data": {
      "nodes": [
        {
          "id": "webhook1",
          "type": "webhookTrigger",
          "position": {"x": 100, "y": 100},
          "properties": {
            "path": "/my-first-webhook",
            "method": "POST"
          }
        },
        {
          "id": "response1",
          "type": "httpRequest",
          "position": {"x": 350, "y": 100},
          "properties": {
            "method": "POST",
            "url": "https://httpbin.org/post",
            "body": "{{input.body}}"
          }
        }
      ],
      "connections": [
        {
          "source": "webhook1",
          "target": "response1",
          "sourceOutput": "main",
          "targetInput": "main"
        }
      ]
    },
    "status": "published",
    "is_active": true
  }'
```

### 2. Test Your Workflow

```bash
# Trigger the webhook
curl -X POST "http://localhost:8000/api/webhooks/1" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hello from my first workflow!",
    "timestamp": "2024-01-15T10:30:00Z"
  }'
```

### 3. Check Execution Results

```bash
# Get workflow executions
curl -X GET "http://localhost:8000/api/executions?workflow_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🎨 API Examples

### Authentication
```bash
# Login
curl -X POST "http://localhost:8000/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'
```

### List Workflows
```bash
curl -X GET "http://localhost:8000/api/workflows" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Get Node Manifest
```bash
curl -X GET "http://localhost:8000/api/nodes/manifest" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Execute Workflow
```bash
curl -X POST "http://localhost:8000/api/workflows/1/execute" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "message": "Test execution"
    }
  }'
```

## 🛠️ Development Workflow

### 1. Create a New Node

```php
<?php

namespace App\Nodes\Custom;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;

class MyCustomNode implements NodeInterface
{
    public function getId(): string
    {
        return 'myCustomNode';
    }

    public function getName(): string
    {
        return 'My Custom Node';
    }

    public function getCategory(): string
    {
        return 'action';
    }

    public function getProperties(): array
    {
        return [
            'message' => [
                'type' => 'string',
                'placeholder' => 'Enter your message',
                'required' => true,
            ],
        ];
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $properties = $context->getProperties();

        return NodeExecutionResult::success([
            'message' => $properties['message'],
            'timestamp' => now()->toISOString(),
        ]);
    }

    // ... other required methods
}
```

### 2. Test Your Node

```php
# Test the node
php artisan tinker

>>> $node = new App\Nodes\Custom\MyCustomNode();
>>> $context = new App\Workflow\Execution\NodeExecutionContext(/* ... */);
>>> $result = $node->execute($context);
>>> echo $result->isSuccess() ? 'Success!' : 'Failed!';
```

## 📊 Monitor Your Workflows

### Check Execution Status
```bash
# Get recent executions
curl -X GET "http://localhost:8000/api/executions?limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### View Workflow Statistics
```bash
# Get workflow stats
curl -X GET "http://localhost:8000/api/workflows/1/statistics" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🚀 Next Steps

### 1. Explore the Demo Data
```bash
# List all workflows
curl -X GET "http://localhost:8000/api/workflows" \
  -H "Authorization: Bearer YOUR_TOKEN"

# View a sample workflow
curl -X GET "http://localhost:8000/api/workflows/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Create Advanced Workflows

#### Email Notification Workflow
```json
{
  "name": "User Registration Notifications",
  "workflow_data": {
    "nodes": [
      {
        "id": "webhook",
        "type": "webhookTrigger",
        "properties": {
          "path": "/user/registered",
          "method": "POST"
        }
      },
      {
        "id": "email",
        "type": "email",
        "properties": {
          "to": "{{input.body.email}}",
          "subject": "Welcome to our platform!",
          "body": "Welcome {{input.body.name}}!"
        }
      }
    ],
    "connections": [
      {
        "source": "webhook",
        "target": "email"
      }
    ]
  }
}
```

#### API Data Sync Workflow
```json
{
  "name": "API Data Synchronization",
  "workflow_data": {
    "nodes": [
      {
        "id": "schedule",
        "type": "scheduleTrigger",
        "properties": {
          "schedule": "0 */6 * * *"
        }
      },
      {
        "id": "api_call",
        "type": "httpRequest",
        "properties": {
          "method": "GET",
          "url": "https://api.example.com/data"
        }
      },
      {
        "id": "database",
        "type": "databaseQuery",
        "properties": {
          "query_type": "insert",
          "table": "sync_data",
          "data": "{{api_call.response}}"
        }
      }
    ],
    "connections": [
      {
        "source": "schedule",
        "target": "api_call"
      },
      {
        "source": "api_call",
        "target": "database"
      }
    ]
  }
}
```

### 3. Set Up Monitoring

```bash
# Check system health
curl -X GET "http://localhost:8000/health"

# View logs
tail -f storage/logs/laravel.log
```

## 🐛 Troubleshooting

### Common Issues

#### 1. Database Connection Error
```bash
# Check database configuration
php artisan config:show database

# Test database connection
php artisan tinker
>>> DB::connection()->getPdo()
```

#### 2. Node Not Found Error
```bash
# Check node registry
php artisan tinker
>>> app(App\Nodes\Registry\NodeRegistry::class)->all()
```

#### 3. Permission Denied
```bash
# Fix storage permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# Fix file ownership
sudo chown -R www-data:www-data /var/www/n8n-ai-lara/
```

#### 4. Queue Not Working
```bash
# Check queue status
php artisan queue:status

# Clear failed jobs
php artisan queue:clear

# Restart queue worker
php artisan queue:restart
```

## 📚 Resources

### Documentation
- **[Installation Guide](./installation-setup.md)** - Complete setup instructions
- **[API Documentation](./api-documentation.md)** - Full API reference
- **[Node System](./node-system.md)** - Create custom nodes
- **[Workflow Engine](./workflow-engine.md)** - Execution engine details

### Sample Data
- **Login Credentials**: See seeder output or check `php artisan n8n:seed-demo`
- **Sample Workflows**: Available in seeded data
- **API Endpoints**: Documented in API docs

### Community
- **GitHub Issues**: Report bugs and request features
- **Discord**: Join the community for help
- **Documentation**: Contribute to docs

## 🎉 You're Ready!

Your n8n clone is now fully operational! You can:

1. ✅ **Create workflows** using the API or web interface
2. ✅ **Execute workflows** via webhooks or manual triggers
3. ✅ **Monitor executions** and view detailed logs
4. ✅ **Build custom nodes** for your specific needs
5. ✅ **Scale your system** as your needs grow

**Happy automating! 🚀**

---

*Need help? Check the [troubleshooting guide](./troubleshooting.md) or join our community.*
