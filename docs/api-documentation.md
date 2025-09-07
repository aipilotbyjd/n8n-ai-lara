# 📡 API Documentation

Complete REST API documentation for the n8n clone workflow automation platform.

## 🔐 Authentication

All API requests require authentication except for webhook endpoints.

### Authentication Methods

#### Bearer Token (Recommended)
```bash
curl -X GET "https://api.your-domain.com/api/workflows" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json"
```

#### API Key (Alternative)
```bash
curl -X GET "https://api.your-domain.com/api/workflows" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Accept: application/json"
```

### Getting Access Tokens

#### Login to get Bearer Token
```bash
curl -X POST "https://api.your-domain.com/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password"
  }'
```

Response:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "1|abc123def456...",
    "token_type": "Bearer"
  }
}
```

## 📊 Response Format

### Success Response
```json
{
  "success": true,
  "data": {
    // Response data
  },
  "meta": {
    // Pagination, additional metadata
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [
    // Array of items
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72,
    "from": 1,
    "to": 15
  }
}
```

## 🎯 Workflows API

### List Workflows

Get all workflows for the authenticated user.

```bash
GET /api/workflows
```

#### Query Parameters
- `page` (integer): Page number for pagination
- `per_page` (integer): Items per page (default: 15, max: 100)
- `status` (string): Filter by status (draft, published, archived)
- `organization_id` (integer): Filter by organization
- `team_id` (integer): Filter by team
- `is_template` (boolean): Filter templates only
- `tags` (string): Filter by tags (comma-separated)
- `search` (string): Search in name and description
- `sort_by` (string): Sort field (default: updated_at)
- `sort_direction` (string): Sort direction (asc, desc)

#### Example Request
```bash
curl -X GET "https://api.your-domain.com/api/workflows?page=1&per_page=10&status=published&search=automation" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

#### Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Customer Support Ticket Processor",
      "slug": "customer-support-ticket-processor-1-1234",
      "description": "Automatically processes customer support tickets...",
      "organization_id": 1,
      "team_id": null,
      "user_id": 1,
      "status": "published",
      "is_active": true,
      "is_template": false,
      "tags": ["support", "automation"],
      "execution_count": 15,
      "last_executed_at": "2024-01-15T10:30:00Z",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-15T10:30:00Z",
      "organization": {
        "id": 1,
        "name": "Acme Corporation",
        "slug": "acme-corporation"
      },
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 48
  }
}
```

### Create Workflow

Create a new workflow.

```bash
POST /api/workflows
```

#### Request Body
```json
{
  "name": "New Workflow",
  "description": "Description of the workflow",
  "organization_id": 1,
  "team_id": null,
  "workflow_data": {
    "nodes": [
      {
        "id": "webhook1",
        "type": "webhookTrigger",
        "position": {"x": 100, "y": 100},
        "properties": {
          "path": "/my-webhook",
          "method": "POST"
        }
      },
      {
        "id": "http1",
        "type": "httpRequest",
        "position": {"x": 350, "y": 100},
        "properties": {
          "method": "GET",
          "url": "https://api.example.com/data"
        }
      }
    ],
    "connections": [
      {
        "source": "webhook1",
        "target": "http1",
        "sourceOutput": "main",
        "targetInput": "main"
      }
    ],
    "settings": {
      "errorHandling": "continue",
      "maxRetries": 3
    }
  },
  "settings": {
    "webhook_response_code": 200,
    "webhook_response_body": {"status": "success"}
  },
  "status": "draft",
  "is_active": false,
  "tags": ["automation", "api"]
}
```

#### Response
```json
{
  "success": true,
  "message": "Workflow created successfully",
  "data": {
    "id": 2,
    "name": "New Workflow",
    "slug": "new-workflow-9876",
    "status": "draft",
    "is_active": false,
    "created_at": "2024-01-15T11:00:00Z"
  }
}
```

### Get Workflow

Get a specific workflow by ID.

```bash
GET /api/workflows/{id}
```

#### Response
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Customer Support Ticket Processor",
    "workflow_data": {
      "nodes": [...],
      "connections": [...],
      "settings": {...}
    },
    "settings": {...},
    "executions": [
      {
        "id": 1,
        "status": "success",
        "started_at": "2024-01-15T10:30:00Z",
        "finished_at": "2024-01-15T10:30:05Z",
        "duration": 5000
      }
    ]
  }
}
```

### Update Workflow

Update an existing workflow.

```bash
PUT /api/workflows/{id}
```

#### Request Body
```json
{
  "name": "Updated Workflow Name",
  "description": "Updated description",
  "status": "published",
  "is_active": true,
  "workflow_data": {
    // Updated workflow structure
  }
}
```

### Delete Workflow

Delete a workflow.

```bash
DELETE /api/workflows/{id}
```

#### Response
```json
{
  "success": true,
  "message": "Workflow deleted successfully"
}
```

### Execute Workflow

Execute a workflow manually or via API.

```bash
POST /api/workflows/{id}/execute
```

#### Query Parameters
- `async` (boolean): Execute asynchronously (default: true)
- `priority` (string): Execution priority (low, normal, high)

#### Request Body
```json
{
  "data": {
    "custom_field": "value",
    "input_data": {
      "user_id": 123,
      "action": "process"
    }
  }
}
```

#### Synchronous Response
```json
{
  "success": true,
  "message": "Workflow executed successfully",
  "data": {
    "result": {
      "output1": "processed data",
      "output2": {"key": "value"}
    },
    "execution_time": 2500,
    "node_executions": 3
  }
}
```

#### Asynchronous Response
```json
{
  "success": true,
  "message": "Workflow execution queued successfully",
  "data": {
    "job_id": "job_abc123",
    "execution_id": 42,
    "estimated_time": 5000
  }
}
```

### Test Execute Workflow

Execute a workflow in test mode (no database changes).

```bash
POST /api/workflows/{id}/test-execute
```

### Duplicate Workflow

Create a copy of an existing workflow.

```bash
POST /api/workflows/{id}/duplicate
```

#### Response
```json
{
  "success": true,
  "message": "Workflow duplicated successfully",
  "data": {
    "id": 3,
    "name": "Customer Support Ticket Processor (Copy)",
    "slug": "customer-support-ticket-processor-copy-1234"
  }
}
```

### Get Workflow Statistics

Get execution statistics for a workflow.

```bash
GET /api/workflows/{id}/statistics
```

#### Response
```json
{
  "success": true,
  "data": {
    "total_executions": 150,
    "successful_executions": 142,
    "failed_executions": 8,
    "success_rate": 94.67,
    "average_execution_time": 2500,
    "last_execution_at": "2024-01-15T11:00:00Z"
  }
}
```

### Export Workflow

Export workflow configuration.

```bash
GET /api/workflows/{id}/export
```

#### Response
```json
{
  "success": true,
  "data": {
    "name": "Customer Support Ticket Processor",
    "description": "Automatically processes customer support tickets...",
    "workflow_data": {...},
    "settings": {...},
    "tags": ["support", "automation"],
    "exported_at": "2024-01-15T11:00:00Z",
    "version": "1.0"
  }
}
```

### Import Workflow

Import a workflow from exported configuration.

```bash
POST /api/workflows/import
```

#### Request Body
```json
{
  "name": "Imported Workflow",
  "workflow_data": {
    // Workflow configuration from export
  },
  "settings": {
    // Workflow settings
  },
  "tags": ["imported"]
}
```

## ⚙️ Executions API

### List Executions

Get all executions for the authenticated user.

```bash
GET /api/executions
```

#### Query Parameters
- `page`, `per_page`: Pagination
- `status`: Filter by status (waiting, running, success, error, canceled)
- `workflow_id`: Filter by workflow
- `organization_id`: Filter by organization
- `mode`: Filter by execution mode (manual, webhook, api, schedule)
- `date_from`, `date_to`: Date range filter

### Get Execution

Get a specific execution.

```bash
GET /api/executions/{id}
```

#### Response
```json
{
  "success": true,
  "data": {
    "id": 1,
    "execution_id": "exec_abc123",
    "workflow_id": 1,
    "status": "success",
    "mode": "webhook",
    "input_data": {...},
    "output_data": {...},
    "error_message": null,
    "duration": 2500,
    "started_at": "2024-01-15T10:30:00Z",
    "finished_at": "2024-01-15T10:30:02Z",
    "logs": [
      {
        "level": "info",
        "message": "Webhook triggered",
        "timestamp": "2024-01-15T10:30:00Z"
      }
    ]
  }
}
```

### Cancel Execution

Cancel a running execution.

```bash
POST /api/executions/{id}/cancel
```

### Retry Execution

Retry a failed execution.

```bash
POST /api/executions/{id}/retry
```

### Get Execution Logs

Get logs for a specific execution.

```bash
GET /api/executions/{id}/logs
```

### Get Execution Statistics

Get overall execution statistics.

```bash
GET /api/executions/statistics
```

#### Query Parameters
- `date_from`: Start date (default: 30 days ago)
- `date_to`: End date (default: today)

#### Response
```json
{
  "success": true,
  "data": {
    "total_executions": 1250,
    "successful_executions": 1180,
    "failed_executions": 70,
    "success_rate": 94.4,
    "average_execution_time": 2300,
    "total_execution_time": 2875000
  }
}
```

## 🔧 Nodes API

### Get Node Manifest

Get all available nodes and their configurations.

```bash
GET /api/nodes/manifest
```

#### Response
```json
{
  "success": true,
  "data": [
    {
      "id": "webhookTrigger",
      "name": "Webhook",
      "version": "1.0.0",
      "category": "trigger",
      "icon": "webhook",
      "description": "Trigger workflow execution via HTTP webhooks",
      "properties": {
        "path": {
          "type": "string",
          "placeholder": "/webhook/my-endpoint",
          "required": false
        }
      },
      "inputs": {
        "main": {
          "type": "object",
          "description": "Webhook payload data"
        }
      },
      "outputs": {
        "main": {
          "type": "object",
          "description": "Webhook data for workflow processing"
        }
      },
      "tags": ["webhook", "trigger", "http", "api"]
    }
  ]
}
```

### Get Node Categories

Get all available node categories.

```bash
GET /api/nodes/categories
```

#### Response
```json
{
  "success": true,
  "data": [
    {
      "name": "trigger",
      "count": 5,
      "nodes": ["webhookTrigger", "scheduleTrigger", "emailTrigger"]
    },
    {
      "name": "action",
      "count": 12,
      "nodes": ["httpRequest", "email", "databaseQuery"]
    }
  ]
}
```

### Get Node Details

Get detailed information about a specific node.

```bash
GET /api/nodes/{nodeId}
```

### Get Node Recommendations

Get recommended nodes for a specific node.

```bash
GET /api/nodes/{nodeId}/recommendations
```

### Validate Node Properties

Validate properties for a specific node type.

```bash
POST /api/nodes/{nodeId}/validate-properties
```

#### Request Body
```json
{
  "properties": {
    "method": "GET",
    "url": "https://api.example.com/data"
  }
}
```

#### Response
```json
{
  "success": true,
  "data": {
    "valid": true,
    "node_id": "httpRequest"
  }
}
```

### Get Node Statistics

Get statistics about the node registry.

```bash
GET /api/nodes/statistics
```

#### Response
```json
{
  "success": true,
  "data": {
    "total_nodes": 25,
    "categories": {
      "trigger": 5,
      "action": 12,
      "transformer": 8
    },
    "categories_count": 3
  }
}
```

## 🎫 Authentication API

### Register User

Create a new user account.

```bash
POST /api/auth/register
```

#### Request Body
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secure_password",
  "password_confirmation": "secure_password"
}
```

### Login

Authenticate and get access token.

```bash
POST /api/auth/login
```

#### Request Body
```json
{
  "email": "john@example.com",
  "password": "password"
}
```

#### Response
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "current_organization_id": 1
    },
    "token": "1|abc123def456...",
    "token_type": "Bearer",
    "expires_at": "2024-01-16T00:00:00Z"
  }
}
```

### Logout

Revoke the current access token.

```bash
POST /api/auth/logout
```

### Logout All

Revoke all access tokens for the user.

```bash
POST /api/auth/logout-all
```

### Get Profile

Get current user profile.

```bash
GET /api/auth/profile
```

### Update Profile

Update user profile information.

```bash
PUT /api/auth/profile
```

#### Request Body
```json
{
  "name": "John Smith",
  "email": "johnsmith@example.com"
}
```

### Change Password

Change user password.

```bash
POST /api/auth/change-password
```

#### Request Body
```json
{
  "current_password": "old_password",
  "password": "new_secure_password",
  "password_confirmation": "new_secure_password"
}
```

### Get Tokens

Get all active access tokens.

```bash
GET /api/auth/tokens
```

### Revoke Token

Revoke a specific access token.

```bash
DELETE /api/auth/tokens/{tokenId}
```

## 🏢 Organizations API

### List Organizations

Get all organizations for the authenticated user.

```bash
GET /api/organizations
```

### Create Organization

Create a new organization.

```bash
POST /api/organizations
```

#### Request Body
```json
{
  "name": "My Company",
  "description": "Company description",
  "settings": {
    "timezone": "America/New_York",
    "language": "en"
  }
}
```

### Get Organization

Get a specific organization.

```bash
GET /api/organizations/{id}
```

### Update Organization

Update organization information.

```bash
PUT /api/organizations/{id}
```

### Delete Organization

Delete an organization.

```bash
DELETE /api/organizations/{id}
```

### Switch Organization

Switch the user's current organization context.

```bash
POST /api/organizations/{id}/switch
```

### Add Organization Member

Add a user to the organization.

```bash
POST /api/organizations/{id}/members
```

#### Request Body
```json
{
  "user_id": 2,
  "role": "admin"
}
```

### Update Member Role

Update a member's role in the organization.

```bash
PUT /api/organizations/{id}/members/{userId}
```

#### Request Body
```json
{
  "role": "member"
}
```

### Remove Member

Remove a user from the organization.

```bash
DELETE /api/organizations/{id}/members/{userId}
```

## 👥 Teams API

### List Teams

Get all teams in an organization.

```bash
GET /api/organizations/{organizationId}/teams
```

### Create Team

Create a new team in an organization.

```bash
POST /api/organizations/{organizationId}/teams
```

#### Request Body
```json
{
  "name": "Engineering Team",
  "description": "Core engineering team",
  "color": "#3B82F6",
  "settings": {
    "department": "engineering"
  }
}
```

### Get Team

Get a specific team.

```bash
GET /api/organizations/{organizationId}/teams/{teamId}
```

### Update Team

Update team information.

```bash
PUT /api/organizations/{organizationId}/teams/{teamId}
```

### Delete Team

Delete a team.

```bash
DELETE /api/organizations/{organizationId}/teams/{teamId}
```

### Add Team Member

Add a user to the team.

```bash
POST /api/organizations/{organizationId}/teams/{teamId}/members
```

### Update Team Member Role

Update a team member's role.

```bash
PUT /api/organizations/{organizationId}/teams/{teamId}/members/{userId}
```

### Remove Team Member

Remove a user from the team.

```bash
DELETE /api/organizations/{organizationId}/teams/{teamId}/members/{userId}
```

## 🔑 Credentials API

### List Credentials

Get all credentials for the authenticated user.

```bash
GET /api/credentials
```

### Create Credential

Create a new credential.

```bash
POST /api/credentials
```

#### Request Body
```json
{
  "name": "GitHub API Token",
  "type": "oauth2",
  "data": "{\"access_token\":\"gh_access_123\",\"token_type\":\"bearer\"}",
  "is_shared": true,
  "expires_at": "2024-02-15T00:00:00Z"
}
```

### Get Credential

Get a specific credential.

```bash
GET /api/credentials/{id}
```

### Update Credential

Update credential information.

```bash
PUT /api/credentials/{id}
```

### Delete Credential

Delete a credential.

```bash
DELETE /api/credentials/{id}
```

## 🌐 Webhooks API

### Trigger Webhook

Trigger a workflow execution via webhook.

```bash
POST /api/webhooks/{workflowId}
GET /api/webhooks/{workflowId}
PUT /api/webhooks/{workflowId}
```

#### Headers
```
Content-Type: application/json
X-Webhook-Signature: sha256=abc123... (optional)
```

#### Example Request
```bash
curl -X POST "https://api.your-domain.com/api/webhooks/1" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "user.created",
    "user": {
      "id": 123,
      "email": "user@example.com",
      "name": "John Doe"
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }'
```

#### Response
```json
{
  "success": true,
  "message": "Workflow executed successfully",
  "data": {
    "execution_id": "exec_abc123",
    "result": {
      "processed": true,
      "user_id": 123,
      "welcome_email_sent": true
    }
  }
}
```

## 📊 Error Handling

### Common HTTP Status Codes

- **200 OK**: Request successful
- **201 Created**: Resource created successfully
- **204 No Content**: Request successful, no content returned
- **400 Bad Request**: Invalid request data
- **401 Unauthorized**: Authentication required
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **422 Unprocessable Entity**: Validation failed
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Server Error**: Server error

### Validation Errors

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

### Rate Limiting

```json
{
  "success": false,
  "message": "Too many requests",
  "errors": {
    "rate_limit": ["API rate limit exceeded. Try again in 60 seconds."]
  }
}
```

## 🔒 Security

### API Key Authentication

For server-to-server communication:

```bash
curl -H "X-API-Key: your-api-key" \
     -H "X-API-Secret: your-api-secret" \
     https://api.your-domain.com/api/workflows
```

### Webhook Security

Webhooks support HMAC signatures for verification:

```php
// Verify webhook signature
$signature = hash_hmac('sha256', $payload, $webhookSecret);
if (hash_equals($signature, $receivedSignature)) {
    // Valid webhook
}
```

### Rate Limiting

Default rate limits:
- **API endpoints**: 60 requests per minute
- **Webhook endpoints**: 100 requests per minute
- **Authentication endpoints**: 10 requests per minute

## 📈 Pagination

All list endpoints support pagination:

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 147,
    "from": 1,
    "to": 15,
    "path": "https://api.your-domain.com/api/workflows",
    "next_page_url": "https://api.your-domain.com/api/workflows?page=2",
    "prev_page_url": null
  }
}
```

## 🎯 Best Practices

### 1. Use Appropriate HTTP Methods
- `GET` for retrieving data
- `POST` for creating resources
- `PUT` for updating resources
- `DELETE` for removing resources

### 2. Handle Errors Gracefully
```javascript
try {
  const response = await fetch('/api/workflows', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(workflowData)
  });

  const result = await response.json();

  if (!result.success) {
    throw new Error(result.message);
  }

  return result.data;
} catch (error) {
  console.error('API Error:', error);
  throw error;
}
```

### 3. Implement Retry Logic
```javascript
async function executeWorkflow(workflowId, data, retries = 3) {
  for (let i = 0; i < retries; i++) {
    try {
      const response = await fetch(`/api/workflows/${workflowId}/execute`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ data })
      });

      if (response.ok) {
        return await response.json();
      }

      if (response.status === 429) {
        // Rate limited, wait before retry
        await new Promise(resolve => setTimeout(resolve, 1000 * (i + 1)));
        continue;
      }

      throw new Error(`HTTP ${response.status}`);
    } catch (error) {
      if (i === retries - 1) throw error;
      await new Promise(resolve => setTimeout(resolve, 1000 * (i + 1)));
    }
  }
}
```

### 4. Use Webhooks for Real-time Updates
```javascript
// Set up webhook endpoint
app.post('/webhooks/workflow-updates', (req, res) => {
  const { workflow_id, execution_id, status, result } = req.body;

  // Process webhook data
  switch (status) {
    case 'success':
      console.log(`Workflow ${workflow_id} completed successfully`);
      break;
    case 'error':
      console.error(`Workflow ${workflow_id} failed:`, result.error);
      break;
    case 'running':
      console.log(`Workflow ${workflow_id} started execution`);
      break;
  }

  res.json({ received: true });
});
```

---

**🚀 Ready to automate? Start building workflows with our comprehensive API!**
