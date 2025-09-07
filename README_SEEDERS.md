# Database Seeders for n8n Clone

This document explains how to use the comprehensive database seeders that populate your n8n clone with realistic sample data.

## 🚀 Quick Start

Run all seeders at once:
```bash
php artisan db:seed
```

Or run specific seeders:
```bash
php artisan db:seed --class=DatabaseSeeder
php artisan db:seed --class=WorkflowSeeder
php artisan db:seed --class=ExecutionSeeder
php artisan db:seed --class=CredentialSeeder
```

## 📊 What Gets Created

### Users (5 users)
- **John Doe** (john@example.com / password)
- **Jane Smith** (jane@example.com / password)
- **Bob Johnson** (bob@example.com / password)
- **Alice Brown** (alice@example.com / password)
- **Charlie Wilson** (charlie@example.com / password)

### Organizations (2 organizations)
1. **Acme Corporation**
   - Owner: John Doe
   - Status: Active (Pro plan)
   - Members: John Doe (admin), Jane Smith (admin), Bob Johnson (member)

2. **TechStart Inc**
   - Owner: Jane Smith
   - Status: Trial (Free plan)
   - Members: Jane Smith (admin), Alice Smith (member)

### Teams (2-3 teams per organization)
- **Engineering** (Blue theme)
- **Product** (Green theme)
- **Marketing** (Orange theme)
- **DevOps** (Red theme)

### Workflows (3-6 workflows per organization)
1. **Customer Support Ticket Processor**
   - Webhook trigger → Database insert → Email notification
   - Tags: support, automation, customer-service

2. **API Data Sync**
   - Scheduled execution → HTTP request → Database operations
   - Tags: api, sync, data, automation

3. **User Registration Welcome Flow**
   - Webhook trigger → Database lookup → Email notification
   - Tags: user, registration, welcome, email

### Template Workflows (Global templates)
- HTTP Request Template
- Database CRUD Template
- Email Notification Template

### Executions (3-8 executions per workflow)
- Mix of successful, failed, and running executions
- Realistic input/output data
- Historical timestamps
- Various execution modes (webhook, api, manual)

### Credentials (2-4 credentials per organization)
- **GitHub API Token** (OAuth2)
- **Slack Bot Token** (API Key)
- **Stripe API Keys** (API Key)
- **SendGrid SMTP** (SMTP)
- **AWS S3 Credentials** (AWS)
- **Twilio SMS API** (API Key)
- **Google Analytics API** (OAuth2)
- **Database Connection** (Database)

## 🔐 Default Login Credentials

After running the seeders, you can log in with:

| Email | Password | Organization | Role |
|-------|----------|--------------|------|
| john@example.com | password | Acme Corporation (Pro) | Admin |
| jane@example.com | password | TechStart Inc (Free Trial) | Admin |
| bob@example.com | password | Acme Corporation (Pro) | Member |
| alice@example.com | password | TechStart Inc (Free Trial) | Member |
| charlie@example.com | password | None | User |

## 🎯 Sample API Usage

### 1. Get All Workflows
```bash
curl -X GET "http://localhost:8000/api/workflows" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### 2. Execute a Workflow
```bash
curl -X POST "http://localhost:8000/api/workflows/1/execute" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"data": {"message": "Test execution"}}'
```

### 3. Trigger Webhook
```bash
curl -X POST "http://localhost:8000/api/webhooks/1" \
  -H "Content-Type: application/json" \
  -d '{"title": "Test Ticket", "description": "Test description", "priority": "high"}'
```

### 4. Get Node Manifest
```bash
curl -X GET "http://localhost:8000/api/nodes/manifest" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 📈 Sample Data Structure

### Workflow Example
```json
{
  "name": "Customer Support Ticket Processor",
  "description": "Automatically processes customer support tickets...",
  "workflow_data": {
    "nodes": [
      {
        "id": "webhook_trigger",
        "type": "webhookTrigger",
        "properties": {
          "path": "/support/ticket",
          "method": "POST"
        }
      },
      {
        "id": "email_notification",
        "type": "email",
        "properties": {
          "to": "support@company.com",
          "subject": "New Support Ticket",
          "body": "A new support ticket has been created..."
        }
      }
    ],
    "connections": [
      {
        "source": "webhook_trigger",
        "target": "email_notification",
        "sourceOutput": "main",
        "targetInput": "main"
      }
    ]
  },
  "status": "published",
  "is_active": true,
  "execution_count": 15
}
```

### Execution Example
```json
{
  "execution_id": "exec_507f1f77bcf86cd799439011_0",
  "status": "success",
  "mode": "webhook",
  "input_data": {
    "method": "POST",
    "url": "/support/ticket",
    "body": {
      "title": "Cannot access dashboard",
      "description": "User unable to log into account",
      "priority": "high"
    }
  },
  "output_data": {
    "ticket_id": 12345,
    "status": "created",
    "assigned_to": "support_team"
  },
  "duration": 245,
  "started_at": "2024-01-15 10:30:00",
  "finished_at": "2024-01-15 10:30:00"
}
```

## 🛠️ Customization

### Adding More Sample Data

1. **Add more users** in `DatabaseSeeder::createUsers()`
2. **Add more workflows** in `WorkflowSeeder::createSampleWorkflows()`
3. **Add more credentials** in `CredentialSeeder::createSampleCredentials()`
4. **Modify execution data** in `ExecutionSeeder::createSampleExecutions()`

### Example: Add a New Workflow Template

```php
// In WorkflowSeeder.php
private function createTemplateWorkflows(): void
{
    // ... existing code ...

    [
        'name' => 'Slack Notification Template',
        'description' => 'Template for sending Slack notifications',
        'workflow_data' => [
            'nodes' => [
                [
                    'id' => 'trigger',
                    'type' => 'webhookTrigger',
                    'properties' => ['path' => '/slack/notify', 'method' => 'POST'],
                ],
                [
                    'id' => 'slack_message',
                    'type' => 'slack',
                    'properties' => [
                        'channel' => '#general',
                        'message' => 'New notification: {{input.body.message}}',
                    ],
                ],
            ],
            'connections' => [
                [
                    'source' => 'trigger',
                    'target' => 'slack_message',
                    'sourceOutput' => 'main',
                    'targetInput' => 'main',
                ],
            ],
        ],
        'tags' => ['template', 'slack', 'notification'],
    ],
}
```

## 🔄 Resetting Data

To reset and reseed the database:
```bash
php artisan migrate:fresh --seed
```

Or reset and seed specific data:
```bash
php artisan migrate:fresh
php artisan db:seed --class=WorkflowSeeder
php artisan db:seed --class=ExecutionSeeder
```

## 📊 Database Statistics

After running all seeders, your database will contain:

- **Users**: 5
- **Organizations**: 2
- **Teams**: 4-6
- **Workflows**: 6-12 (including templates)
- **Executions**: 20-60
- **Credentials**: 4-8
- **Plans**: 3 (Free, Pro, Enterprise)

## 🎯 Next Steps

1. **Test the API endpoints** using the sample data
2. **Create your own workflows** using the templates as starting points
3. **Experiment with webhook triggers** using the sample endpoints
4. **Monitor executions** through the API to see real execution data
5. **Customize credentials** to connect to your actual services

Your n8n clone is now fully populated with realistic sample data! 🚀
