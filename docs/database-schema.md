# 🗄️ Database Schema Documentation

Complete database schema documentation for the n8n clone workflow automation platform.

## 📊 Overview

The n8n clone uses a relational database (PostgreSQL/MySQL) with optimized schema design for high-performance workflow execution and management.

## 🏗️ Core Tables

### Users Table

Primary user management table with authentication and profile data.

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    current_organization_id BIGINT UNSIGNED NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (current_organization_id) REFERENCES organizations(id) ON DELETE SET NULL,

    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_current_org (current_organization_id),
    INDEX idx_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `current_organization_id` → `organizations.id`
- Many-to-many with organizations via `organization_users`
- Many-to-many with teams via `team_users`

**Indexes:**
- Email for login queries
- Role for permission filtering
- Organization for tenant isolation
- Created date for analytics

### Organizations Table

Multi-tenant organization management with subscription data.

```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    subscription_status ENUM('trial', 'active', 'past_due', 'canceled', 'unpaid') DEFAULT 'trial',
    subscription_plan VARCHAR(100) NULL,
    trial_ends_at TIMESTAMP NULL,
    subscription_ends_at TIMESTAMP NULL,
    settings JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_organizations_slug (slug),
    INDEX idx_organizations_owner (owner_id),
    INDEX idx_organizations_status (subscription_status),
    INDEX idx_organizations_trial_ends (trial_ends_at),
    INDEX idx_organizations_active (is_active),
    UNIQUE KEY unique_org_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `owner_id` → `users.id`
- One-to-many with teams
- One-to-many with workflows
- One-to-many with credentials
- Many-to-many with users via `organization_users`

**Settings JSON Structure:**
```json
{
  "timezone": "America/New_York",
  "language": "en",
  "theme": "light",
  "max_workflows": 100,
  "max_executions_per_month": 10000,
  "features": ["webhooks", "api", "scheduling"]
}
```

### Teams Table

Team management within organizations for collaboration.

```sql
CREATE TABLE teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    settings JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_teams_organization (organization_id),
    INDEX idx_teams_owner (owner_id),
    INDEX idx_teams_active (is_active),
    UNIQUE KEY unique_team_slug_org (organization_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `organization_id` → `organizations.id`
- `owner_id` → `users.id`
- One-to-many with workflows
- Many-to-many with users via `team_users`

**Settings JSON Structure:**
```json
{
  "department": "engineering",
  "notification_channels": ["slack", "email"],
  "auto_assignment": true,
  "max_members": 20
}
```

### Workflows Table

Core workflow definition and metadata storage.

```sql
CREATE TABLE workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    workflow_data JSON NOT NULL,
    settings JSON NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_active BOOLEAN DEFAULT TRUE,
    is_template BOOLEAN DEFAULT FALSE,
    version INT DEFAULT 1,
    tags JSON NULL,
    last_executed_at TIMESTAMP NULL,
    execution_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_workflows_organization (organization_id),
    INDEX idx_workflows_team (team_id),
    INDEX idx_workflows_user (user_id),
    INDEX idx_workflows_status (status),
    INDEX idx_workflows_active (is_active),
    INDEX idx_workflows_template (is_template),
    INDEX idx_workflows_last_executed (last_executed_at),
    INDEX idx_workflows_org_status (organization_id, status),
    INDEX idx_workflows_team_status (team_id, status),
    INDEX idx_workflows_user_status (user_id, status),
    UNIQUE KEY unique_workflow_slug_org (organization_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Workflow Data JSON Structure:**
```json
{
  "nodes": [
    {
      "id": "webhook1",
      "type": "webhookTrigger",
      "position": {"x": 100, "y": 100},
      "properties": {
        "path": "/webhook",
        "method": "POST",
        "authentication": "none"
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
    "maxRetries": 3,
    "timeout": 30000
  }
}
```

### Executions Table

Workflow execution tracking and results storage.

```sql
CREATE TABLE executions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    execution_id VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('waiting', 'running', 'success', 'error', 'canceled') DEFAULT 'waiting',
    mode ENUM('manual', 'webhook', 'api', 'schedule') DEFAULT 'manual',
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    duration INT NULL COMMENT 'Duration in milliseconds',
    input_data JSON NULL,
    output_data JSON NULL,
    error_message TEXT NULL,
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_executions_workflow (workflow_id),
    INDEX idx_executions_organization (organization_id),
    INDEX idx_executions_user (user_id),
    INDEX idx_executions_status (status),
    INDEX idx_executions_mode (mode),
    INDEX idx_executions_started (started_at),
    INDEX idx_executions_finished (finished_at),
    INDEX idx_executions_duration (duration),
    INDEX idx_executions_workflow_status (workflow_id, status),
    INDEX idx_executions_org_status (organization_id, status),
    INDEX idx_executions_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Execution Metadata JSON Structure:**
```json
{
  "user_agent": "n8n-clone/1.0",
  "ip_address": "192.168.1.100",
  "execution_version": "1.0.0",
  "node_versions": {
    "webhookTrigger": "1.0.0",
    "httpRequest": "1.0.0"
  },
  "resource_usage": {
    "cpu_ms": 150,
    "memory_mb": 25,
    "network_kb": 5
  }
}
```

### Execution Logs Table

Detailed execution logging for debugging and monitoring.

```sql
CREATE TABLE execution_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    execution_id BIGINT UNSIGNED NOT NULL,
    node_id VARCHAR(255) NULL,
    level ENUM('debug', 'info', 'warning', 'error') DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (execution_id) REFERENCES executions(id) ON DELETE CASCADE,

    INDEX idx_execution_logs_execution (execution_id),
    INDEX idx_execution_logs_node (execution_id, node_id),
    INDEX idx_execution_logs_level (execution_id, level),
    INDEX idx_execution_logs_timestamp (execution_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Credentials Table

Secure storage for API keys, tokens, and authentication credentials.

```sql
CREATE TABLE credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL COMMENT 'oauth2, api_key, basic_auth, bearer_token, custom',
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    data TEXT NOT NULL COMMENT 'Encrypted JSON data',
    is_shared BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_credentials_organization (organization_id),
    INDEX idx_credentials_user (user_id),
    INDEX idx_credentials_type (type),
    INDEX idx_credentials_shared (is_shared),
    INDEX idx_credentials_expires (expires_at),
    INDEX idx_credentials_last_used (last_used_at),
    INDEX idx_credentials_org_type (organization_id, type),
    INDEX idx_credentials_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🔗 Junction Tables

### Organization Users Table

Many-to-many relationship between organizations and users with roles.

```sql
CREATE TABLE organization_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    invited_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_org_users_organization (organization_id),
    INDEX idx_org_users_user (user_id),
    INDEX idx_org_users_role (role),
    INDEX idx_org_users_joined (joined_at),
    UNIQUE KEY unique_org_user (organization_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Team Users Table

Many-to-many relationship between teams and users with roles.

```sql
CREATE TABLE team_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_team_users_team (team_id),
    INDEX idx_team_users_user (user_id),
    INDEX idx_team_users_role (role),
    INDEX idx_team_users_joined (joined_at),
    UNIQUE KEY unique_team_user (team_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 📊 Analytics & Reporting Tables

### Plans Table

Subscription plan definitions for billing.

```sql
CREATE TABLE plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'usd',
    interval ENUM('day', 'week', 'month', 'year') DEFAULT 'month',
    interval_count INT DEFAULT 1,
    trial_days INT DEFAULT 0,
    features JSON NULL,
    limits JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_plans_slug (slug),
    INDEX idx_plans_active (is_active),
    INDEX idx_plans_sort_order (sort_order),
    UNIQUE KEY unique_plan_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Limits JSON Structure:**
```json
{
  "workflows": 100,
  "executions_per_month": 10000,
  "team_members": 10,
  "credentials": 50,
  "storage_gb": 5,
  "api_calls_per_hour": 1000
}
```

### Subscriptions Table

User subscription tracking for billing.

```sql
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    stripe_id VARCHAR(255) UNIQUE NULL,
    stripe_price VARCHAR(255) NULL,
    stripe_status VARCHAR(50) NULL,
    quantity INT DEFAULT 1,
    trial_ends_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    next_billing_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,

    INDEX idx_subscriptions_organization (organization_id),
    INDEX idx_subscriptions_plan (plan_id),
    INDEX idx_subscriptions_stripe (stripe_id),
    INDEX idx_subscriptions_status (stripe_status),
    INDEX idx_subscriptions_trial_ends (trial_ends_at),
    INDEX idx_subscriptions_ends (ends_at),
    INDEX idx_subscriptions_next_billing (next_billing_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Invoices Table

Billing invoice tracking.

```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    stripe_id VARCHAR(255) UNIQUE NULL,
    stripe_invoice_id VARCHAR(255) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'usd',
    status ENUM('draft', 'open', 'paid', 'void', 'uncollectible') DEFAULT 'draft',
    billing_reason VARCHAR(50) NULL,
    description TEXT NULL,
    period_start TIMESTAMP NULL,
    period_end TIMESTAMP NULL,
    due_date TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,

    INDEX idx_invoices_organization (organization_id),
    INDEX idx_invoices_subscription (subscription_id),
    INDEX idx_invoices_stripe (stripe_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_period (period_start, period_end),
    INDEX idx_invoices_due (due_date),
    INDEX idx_invoices_paid (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🔍 Indexes & Performance

### Composite Indexes

```sql
-- Workflow queries
CREATE INDEX idx_workflows_org_status_active ON workflows(organization_id, status, is_active);
CREATE INDEX idx_workflows_team_status_active ON workflows(team_id, status, is_active);
CREATE INDEX idx_workflows_user_status_active ON workflows(user_id, status, is_active);

-- Execution analytics
CREATE INDEX idx_executions_org_status_date ON executions(organization_id, status, created_at);
CREATE INDEX idx_executions_workflow_status_date ON executions(workflow_id, status, created_at);
CREATE INDEX idx_executions_user_status_date ON executions(user_id, status, created_at);

-- Credential access patterns
CREATE INDEX idx_credentials_org_type_active ON credentials(organization_id, type, is_shared);
CREATE INDEX idx_credentials_user_type_active ON credentials(user_id, type, is_shared);
```

### Partitioning Strategy

```sql
-- Partition executions by month for better performance
ALTER TABLE executions PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027)
);

-- Partition execution logs by execution_id for faster queries
ALTER TABLE execution_logs PARTITION BY HASH(execution_id) PARTITIONS 16;
```

## 🔐 Security Considerations

### Data Encryption

```sql
-- Sensitive data is encrypted at application level
-- Credentials data is encrypted using Laravel's Crypt facade
-- API keys and tokens are never stored in plain text
-- Database-level encryption for highly sensitive data
```

### Access Control

```sql
-- Row Level Security (RLS) for multi-tenant data
-- All queries include organization_id filtering
-- Users can only access data from their organizations
-- API tokens are scoped to specific organizations
```

### Audit Trail

```sql
-- All critical operations are logged
-- User actions tracked in execution_logs
-- API access logged for security monitoring
-- Data changes tracked for compliance
```

## 📈 Optimization Strategies

### Query Optimization

```sql
-- Use covering indexes for common queries
CREATE INDEX idx_workflows_covering ON workflows (
    organization_id, status, is_active, name, created_at
);

-- Optimize JSON queries
CREATE INDEX idx_workflows_tags ON workflows (
    (tags->>'$.category'),
    (tags->>'$.priority')
);
```

### Caching Strategy

```sql
-- Cache frequently accessed data
-- Workflow definitions cached in Redis
-- User permissions cached with tags
-- API responses cached with TTL
-- Database query results cached
```

### Archival Strategy

```sql
-- Archive old executions after 90 days
CREATE TABLE executions_archive LIKE executions;

-- Move old data to archive table
INSERT INTO executions_archive
SELECT * FROM executions
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Delete archived data
DELETE FROM executions
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## 🔄 Migration Strategy

### Zero-Downtime Migrations

```php
// Use Laravel's zero-downtime migration features
Schema::whenTableDoesntHaveColumn('workflows', 'version', function (Blueprint $table) {
    $table->integer('version')->default(1)->after('is_template');
});

// Create new table, copy data, rename
Schema::create('workflows_new', function (Blueprint $table) {
    // New schema
});

DB::statement('INSERT INTO workflows_new SELECT * FROM workflows');

Schema::drop('workflows');
Schema::rename('workflows_new', 'workflows');
```

### Rollback Strategy

```php
// Always provide rollback scripts
public function down(): void
{
    Schema::table('workflows', function (Blueprint $table) {
        $table->dropIndex('idx_workflows_new_field');
        $table->dropColumn('new_field');
    });
}
```

## 📊 Monitoring Queries

### Performance Metrics

```sql
-- Query execution performance
SELECT
    workflow_id,
    COUNT(*) as total_executions,
    AVG(duration) as avg_duration,
    MIN(duration) as min_duration,
    MAX(duration) as max_duration,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate
FROM executions
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY workflow_id
ORDER BY total_executions DESC;

-- Database performance
SELECT
    table_name,
    table_rows,
    data_length / 1024 / 1024 as data_mb,
    index_length / 1024 / 1024 as index_mb,
    (data_length + index_length) / 1024 / 1024 as total_mb
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY total_mb DESC;
```

### Health Check Queries

```sql
-- Check for orphaned records
SELECT COUNT(*) as orphaned_executions
FROM executions e
LEFT JOIN workflows w ON e.workflow_id = w.id
WHERE w.id IS NULL;

-- Check for data consistency
SELECT
    COUNT(DISTINCT organization_id) as unique_orgs_in_executions,
    (SELECT COUNT(*) FROM organizations) as total_orgs
FROM executions;
```

## 🚀 Scaling Considerations

### Read/Write Splitting

```sql
-- Configure read replicas
'read' => [
    'host' => env('DB_READ_HOST', env('DB_HOST')),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],

// Use read connection for analytics
$analytics = DB::connection('read')
    ->table('executions')
    ->where('organization_id', $orgId)
    ->get();
```

### Database Sharding

```sql
// Shard by organization_id
$shard = $organizationId % 4;
$config = config("database.shards.{$shard}");

// Connect to specific shard
DB::purge('shard');
Config::set('database.connections.shard', $config);
DB::reconnect('shard');
```

### Connection Pooling

```php
// Configure connection pooling
'database' => [
    'connections' => [
        'mysql' => [
            'options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_PERSISTENT => true,
            ],
            'pool_size' => 10,
            'pool_timeout' => 30,
        ],
    ],
],
```

## 🔧 Maintenance Scripts

### Database Cleanup

```sql
-- Remove old execution logs (keep last 30 days)
DELETE FROM execution_logs
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Remove old execution data (keep last 90 days)
DELETE FROM executions
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Optimize tables
OPTIMIZE TABLE executions, execution_logs, workflows;
```

### Index Maintenance

```sql
-- Rebuild fragmented indexes
ALTER TABLE executions ENGINE = InnoDB;
ALTER TABLE workflows ENGINE = InnoDB;

-- Update index statistics
ANALYZE TABLE executions, workflows, credentials;
```

---

**🗄️ This schema is optimized for high-performance workflow execution with proper indexing, partitioning, and scaling strategies.**
