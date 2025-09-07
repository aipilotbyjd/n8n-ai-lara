# 🚀 Deployment Guide

Complete production deployment guide for the n8n clone workflow automation platform.

## 🎯 Deployment Overview

This guide covers deploying the n8n clone to production environments using modern DevOps practices, containerization, and orchestration.

## 🏗️ Deployment Strategies

### 1. Single Server Deployment

#### Docker Compose (Recommended for small deployments)

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile.prod
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./storage:/var/www/html/storage
      - ./logs:/var/log/nginx
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_CONNECTION=mysql
      - DB_HOST=mysql
      - DB_DATABASE=n8n_clone
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - CACHE_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - MAIL_MAILER=smtp
      - MAIL_HOST=${MAIL_HOST}
    depends_on:
      - mysql
      - redis
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: n8n_clone
      MYSQL_USER: ${MYSQL_USER}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/my.cnf
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 30s
      timeout: 10s
      retries: 3

  redis:
    image: redis:7.0-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 30s
      timeout: 10s
      retries: 3

  worker:
    build:
      context: .
      dockerfile: Dockerfile.worker
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_CONNECTION=mysql
      - DB_HOST=mysql
      - DB_DATABASE=n8n_clone
      - DB_USERNAME=${MYSQL_USER}
      - DB_PASSWORD=${MYSQL_PASSWORD}
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
    depends_on:
      - mysql
      - redis
    restart: unless-stopped
    command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-jobs=1000"]
    deploy:
      replicas: 3

volumes:
  mysql_data:
  redis_data:

networks:
  default:
    driver: bridge
```

#### Dockerfile for Production

```dockerfile
# Dockerfile.prod
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    redis \
    git \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Install Node.js dependencies and build assets
RUN npm ci --production && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Configure Nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/sites-available/app.conf /etc/nginx/sites-available/default

# Configure Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose ports
EXPOSE 80 443

# Start services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### 2. Kubernetes Deployment

#### Namespace and ConfigMaps

```yaml
# k8s/namespace.yml
apiVersion: v1
kind: Namespace
metadata:
  name: n8n-clone
  labels:
    name: n8n-clone
```

```yaml
# k8s/configmap.yml
apiVersion: v1
kind: ConfigMap
metadata:
  name: n8n-clone-config
  namespace: n8n-clone
data:
  APP_ENV: "production"
  DB_CONNECTION: "pgsql"
  CACHE_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  LOG_CHANNEL: "stack"
  # Add other environment variables
```

```yaml
# k8s/secret.yml
apiVersion: v1
kind: Secret
metadata:
  name: n8n-clone-secret
  namespace: n8n-clone
type: Opaque
data:
  APP_KEY: <base64-encoded-app-key>
  DB_PASSWORD: <base64-encoded-db-password>
  REDIS_PASSWORD: <base64-encoded-redis-password>
  MAIL_PASSWORD: <base64-encoded-mail-password>
```

#### PostgreSQL Deployment

```yaml
# k8s/postgres.yml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: postgres
  namespace: n8n-clone
spec:
  serviceName: postgres
  replicas: 1
  selector:
    matchLabels:
      app: postgres
  template:
    metadata:
      labels:
        app: postgres
    spec:
      containers:
      - name: postgres
        image: postgres:15-alpine
        ports:
        - containerPort: 5432
        env:
        - name: POSTGRES_DB
          value: "n8n_clone"
        - name: POSTGRES_USER
          valueFrom:
            secretKeyRef:
              name: n8s-clone-secret
              key: DB_USERNAME
        - name: POSTGRES_PASSWORD
          valueFrom:
            secretKeyRef:
              name: n8n-clone-secret
              key: DB_PASSWORD
        volumeMounts:
        - name: postgres-storage
          mountPath: /var/lib/postgresql/data
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
  volumeClaimTemplates:
  - metadata:
    name: postgres-storage
    spec:
      accessModes: ["ReadWriteOnce"]
      resources:
        requests:
          storage: 50Gi
```

#### Redis Deployment

```yaml
# k8s/redis.yml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  namespace: n8n-clone
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
      - name: redis
        image: redis:7.0-alpine
        ports:
        - containerPort: 6379
        command: ["redis-server", "--appendonly", "yes"]
        volumeMounts:
        - name: redis-storage
          mountPath: /data
        resources:
          requests:
            memory: "128Mi"
            cpu: "100m"
          limits:
            memory: "256Mi"
            cpu: "200m"
      volumes:
      - name: redis-storage
        persistentVolumeClaim:
          claimName: redis-pvc
```

#### Application Deployment

```yaml
# k8s/app.yml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: n8n-clone-app
  namespace: n8n-clone
spec:
  replicas: 3
  selector:
    matchLabels:
      app: n8n-clone
  template:
    metadata:
      labels:
        app: n8n-clone
    spec:
      containers:
      - name: app
        image: your-registry/n8n-clone:latest
        ports:
        - containerPort: 80
        envFrom:
        - configMapRef:
            name: n8n-clone-config
        - secretRef:
            name: n8n-clone-secret
        volumeMounts:
        - name: storage
          mountPath: /var/www/html/storage
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
      volumes:
      - name: storage
        persistentVolumeClaim:
          claimName: app-storage
```

#### Horizontal Pod Autoscaler

```yaml
# k8s/hpa.yml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: n8n-clone-hpa
  namespace: n8n-clone
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: n8n-clone-app
  minReplicas: 3
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
```

#### Ingress Configuration

```yaml
# k8s/ingress.yml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: n8n-clone-ingress
  namespace: n8n-clone
  annotations:
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
spec:
  ingressClassName: nginx
  tls:
  - hosts:
    - your-domain.com
    secretName: n8n-clone-tls
  rules:
  - host: your-domain.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: n8n-clone-service
            port:
              number: 80
```

### 3. Cloud-Native Deployment

#### AWS ECS with Fargate

```hcl
# Terraform configuration for ECS
resource "aws_ecs_cluster" "n8n_clone" {
  name = "n8n-clone-cluster"
}

resource "aws_ecs_task_definition" "app" {
  family                   = "n8n-clone-app"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "256"
  memory                   = "512"

  container_definitions = jsonencode([
    {
      name  = "app"
      image = "your-registry/n8n-clone:latest"
      portMappings = [
        {
          containerPort = 80
          hostPort      = 80
        }
      ]
      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "DB_CONNECTION", value = "mysql" },
        { name = "DB_HOST", value = var.db_host },
      ]
      secrets = [
        { name = "APP_KEY", valueFrom = aws_secretsmanager_secret.app_key.arn },
        { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
      ]
    }
  ])
}

resource "aws_ecs_service" "app" {
  name            = "n8n-clone-app"
  cluster         = aws_ecs_cluster.n8n_clone.id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = 3

  network_configuration {
    subnets         = aws_subnet.private.*.id
    security_groups = [aws_security_group.app.id]
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "app"
    container_port   = 80
  }
}
```

#### Google Cloud Run

```yaml
# cloud-run.yml
apiVersion: serving.knative.dev/v1
kind: Service
metadata:
  name: n8n-clone
spec:
  template:
    spec:
      containers:
      - image: your-registry/n8n-clone:latest
        ports:
        - name: http1
          containerPort: 80
        env:
        - name: APP_ENV
          value: "production"
        - name: DB_CONNECTION
          value: "pgsql"
        - name: DB_HOST
          value: "/cloudsql/YOUR_PROJECT:YOUR_REGION:YOUR_INSTANCE"
        resources:
          limits:
            cpu: "1000m"
            memory: "1Gi"
        startupProbe:
          httpGet:
            path: /health
          initialDelaySeconds: 30
          periodSeconds: 10
        livenessProbe:
          httpGet:
            path: /health
          periodSeconds: 30
```

## 🔧 Infrastructure Setup

### 1. SSL/TLS Configuration

#### Let's Encrypt with Certbot

```bash
# Install Certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal (add to crontab)
0 12 * * * /usr/bin/certbot renew --quiet
```

#### AWS Certificate Manager

```hcl
# Terraform ACM certificate
resource "aws_acm_certificate" "cert" {
  domain_name       = "your-domain.com"
  validation_method = "DNS"

  subject_alternative_names = [
    "*.your-domain.com",
  ]

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_route53_record" "cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.cert.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      record = dvo.resource_record_value
      type   = dvo.resource_record_type
    }
  }

  zone_id = aws_route53_zone.main.zone_id
  name    = each.value.name
  type    = each.value.type
  records = [each.value.record]
  ttl     = 60
}
```

### 2. Database Configuration

#### PostgreSQL Production Setup

```sql
-- Create production database
CREATE DATABASE n8n_clone_production
    WITH OWNER = n8n_user
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE = template0;

-- Create user and grant permissions
CREATE USER n8n_user WITH ENCRYPTED PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE n8n_clone_production TO n8n_user;
GRANT ALL ON SCHEMA public TO n8n_user;

-- Enable extensions
\c n8n_clone_production
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";
CREATE EXTENSION IF NOT EXISTS "pg_buffercache";

-- Configure connection pooling
ALTER SYSTEM SET max_connections = '200';
ALTER SYSTEM SET shared_preload_libraries = 'pg_stat_statements';
ALTER SYSTEM SET pg_stat_statements.max = 10000;
ALTER SYSTEM SET pg_stat_statements.track = all;
```

#### MySQL Production Setup

```sql
-- Create production database
CREATE DATABASE n8n_clone_production
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'n8n_user'@'%' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON n8n_clone_production.* TO 'n8n_user'@'%';
FLUSH PRIVILEGES;

-- Configure performance settings
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
SET GLOBAL innodb_log_file_size = 268435456;     -- 256MB
SET GLOBAL max_connections = 200;
```

### 3. Redis Configuration

#### Production Redis Setup

```redis.conf
# Production Redis configuration
bind 127.0.0.1
protected-mode yes
port 6379
timeout 300
tcp-keepalive 300
daemonize yes
supervised systemd
loglevel notice
logfile /var/log/redis/redis.log

# Memory management
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Security
requirepass your_secure_redis_password

# Performance
tcp-backlog 511
databases 16
```

#### Redis Cluster Setup

```bash
# Create Redis cluster
redis-cli --cluster create \
  127.0.0.1:7001 \
  127.0.0.1:7002 \
  127.0.0.1:7003 \
  127.0.0.1:7004 \
  127.0.0.1:7005 \
  127.0.0.1:7006 \
  --cluster-replicas 1
```

### 4. Queue Worker Configuration

#### Supervisor Configuration

```ini
# /etc/supervisor/conf.d/n8n-worker.conf
[program:n8n-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/n8n-clone/artisan queue:work --sleep=3 --tries=3 --max-jobs=1000 --timeout=90
directory=/var/www/n8n-clone
autostart=true
autorestart=true
numprocs=4
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/n8n-clone/storage/logs/worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
```

#### PM2 Configuration

```json
// ecosystem.config.js
module.exports = {
  apps: [{
    name: 'n8n-worker',
    script: 'artisan',
    args: 'queue:work --sleep=3 --tries=3 --max-jobs=1000 --timeout=90',
    instances: 4,
    exec_mode: 'fork',
    cwd: '/var/www/n8n-clone',
    env: {
      APP_ENV: 'production'
    },
    log_file: '/var/www/n8n-clone/storage/logs/worker.log',
    out_file: '/var/www/n8n-clone/storage/logs/worker-out.log',
    error_file: '/var/www/n8n-clone/storage/logs/worker-error.log',
    merge_logs: true,
    time: true,
    max_memory_restart: '1G',
    restart_delay: 4000,
    max_restarts: 10,
    min_uptime: '10s'
  }]
}
```

## 📊 Monitoring & Observability

### 1. Application Monitoring

#### Laravel Telescope Setup

```bash
# Install Telescope
composer require laravel/telescope

# Publish and run migrations
php artisan telescope:install
php artisan migrate

# Configure Telescope (config/telescope.php)
return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    'middleware' => ['web', 'api'],
    'watchers' => [
        // Enable desired watchers
    ],
];
```

#### Custom Metrics

```php
// app/Services/MetricsService.php
class MetricsService
{
    public function recordExecutionMetrics(Execution $execution): void
    {
        $metrics = [
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'duration' => $execution->duration,
            'success' => $execution->status === 'success',
            'node_count' => $execution->logs()->count(),
            'timestamp' => now(),
        ];

        // Send to monitoring service
        $this->sendToMonitoring($metrics);
    }

    private function sendToMonitoring(array $metrics): void
    {
        // DataDog, New Relic, CloudWatch, etc.
        if (config('monitoring.provider') === 'datadog') {
            \DataDog\Metrics::gauge('workflow.execution.duration', $metrics['duration'], [
                'workflow_id' => $metrics['workflow_id'],
                'success' => $metrics['success'],
            ]);
        }
    }
}
```

### 2. Infrastructure Monitoring

#### Prometheus Configuration

```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'n8n-clone-app'
    static_configs:
      - targets: ['app:80']
    metrics_path: '/metrics'
    params:
      format: ['prometheus']

  - job_name: 'n8n-clone-worker'
    static_configs:
      - targets: ['worker:80']

  - job_name: 'postgres'
    static_configs:
      - targets: ['postgres:9187']

  - job_name: 'redis'
    static_configs:
      - targets: ['redis:9121']
```

#### Grafana Dashboards

```json
// Custom dashboard for workflow metrics
{
  "dashboard": {
    "title": "n8n Clone - Workflow Metrics",
    "panels": [
      {
        "title": "Workflow Executions",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(workflow_executions_total[5m])",
            "legendFormat": "Executions per second"
          }
        ]
      },
      {
        "title": "Execution Duration",
        "type": "heatmap",
        "targets": [
          {
            "expr": "workflow_execution_duration_seconds",
            "legendFormat": "{{le}}"
          }
        ]
      }
    ]
  }
}
```

### 3. Logging Configuration

#### Structured Logging Setup

```php
// config/logging.php
return [
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'daily'],
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],
    ],
];
```

#### Log Shipping

```yaml
# filebeat.yml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /var/www/n8n-clone/storage/logs/*.log
  fields:
    service: n8n-clone
    environment: production

output.elasticsearch:
  hosts: ["elasticsearch:9200"]
  index: "n8n-clone-%{+yyyy.MM.dd}"
```

## 🔄 Backup & Recovery

### 1. Database Backup

#### PostgreSQL Backup Script

```bash
#!/bin/bash
# /usr/local/bin/backup-postgres.sh

BACKUP_DIR="/var/backups/n8n-clone"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/n8n_clone_$DATE.sql.gz"

# Create backup directory
mkdir -p $BACKUP_DIR

# Perform backup
pg_dump -h localhost -U n8n_user -d n8n_clone_production | gzip > $BACKUP_FILE

# Upload to cloud storage
aws s3 cp $BACKUP_FILE s3://your-backup-bucket/

# Clean old backups (keep last 30 days)
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

# Send notification
curl -X POST -H 'Content-type: application/json' \
  --data "{\"text\":\"Database backup completed: $BACKUP_FILE\"}" \
  $SLACK_WEBHOOK_URL
```

#### MySQL Backup Script

```bash
#!/bin/bash
# /usr/local/bin/backup-mysql.sh

BACKUP_DIR="/var/backups/n8n-clone"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/n8n_clone_$DATE.sql.gz"

# Create backup directory
mkdir -p $BACKUP_DIR

# Perform backup
mysqldump -u n8n_user -p$MYSQL_PASSWORD n8n_clone_production | gzip > $BACKUP_FILE

# Upload to cloud storage
aws s3 cp $BACKUP_FILE s3://your-backup-bucket/

# Clean old backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete
```

### 2. File System Backup

```bash
#!/bin/bash
# /usr/local/bin/backup-files.sh

BACKUP_DIR="/var/backups/n8n-clone"
SOURCE_DIR="/var/www/n8n-clone"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup application files
tar -czf $BACKUP_DIR/app_$DATE.tar.gz -C $SOURCE_DIR .

# Backup storage files
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C $SOURCE_DIR/storage .

# Upload to cloud storage
aws s3 cp $BACKUP_DIR/app_$DATE.tar.gz s3://your-backup-bucket/
aws s3 cp $BACKUP_DIR/storage_$DATE.tar.gz s3://your-backup-bucket/

# Clean old backups
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 3. Automated Backup Schedule

```bash
# Add to crontab
0 2 * * * /usr/local/bin/backup-postgres.sh
0 3 * * * /usr/local/bin/backup-files.sh
0 4 * * 0 /usr/local/bin/backup-full.sh  # Weekly full backup
```

## 🚨 Disaster Recovery

### Recovery Procedures

#### Database Recovery

```bash
# Stop application
docker-compose stop app worker

# Restore database from backup
gunzip < /var/backups/n8n-clone/n8n_clone_20240115_020000.sql.gz | \
  psql -h localhost -U n8n_user -d n8n_clone_production

# Start application
docker-compose start app worker
```

#### Application Recovery

```bash
# Pull latest stable version
git checkout main
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --production
npm run build

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Restart services
sudo systemctl restart nginx
sudo systemctl restart supervisor
```

### High Availability Setup

#### Load Balancer Configuration

```nginx
# /etc/nginx/sites-available/n8n-clone
upstream n8n_backend {
    ip_hash;
    server 10.0.1.10:80 weight=3;
    server 10.0.1.11:80 weight=3;
    server 10.0.1.12:80 weight=2;
    server 10.0.2.10:80 backup;
}

server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://n8n_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Database Replication

```sql
-- Enable PostgreSQL streaming replication
-- Primary server postgresql.conf
wal_level = replica
max_wal_senders = 3
wal_keep_segments = 64

-- Standby server recovery.conf
primary_conninfo = 'host=primary_ip port=5432 user=replication_user'
standby_mode = 'on'
```

## 📈 Performance Optimization

### 1. Application Optimization

#### Laravel Optimization

```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Clear optimized files for updates
php artisan optimize:clear
```

#### PHP Optimization

```ini
# /etc/php/8.2/fpm/php.ini
memory_limit = 256M
max_execution_time = 300
max_input_time = 60
post_max_size = 50M
upload_max_filesize = 50M

# OPcache settings
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 7963
opcache.revalidate_freq = 0
opcache.fast_shutdown = 1
```

### 2. Database Optimization

#### Query Optimization

```sql
-- Add performance indexes
CREATE INDEX CONCURRENTLY idx_workflows_org_status_active
ON workflows(organization_id, status, is_active)
WHERE is_active = true;

CREATE INDEX CONCURRENTLY idx_executions_workflow_created
ON executions(workflow_id, created_at DESC);

-- Partition large tables
CREATE TABLE executions_y2024 PARTITION OF executions
    FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
```

#### Connection Pooling

```php
// config/database.php
'connections' => [
    'mysql' => [
        'options' => [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_PERSISTENT => true,
        ],
    ],
],
```

### 3. Caching Strategy

#### Multi-Level Caching

```php
// Cache configuration
'cache' => [
    'default' => env('CACHE_DRIVER', 'redis'),
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
    ],
],
```

## 🔒 Security Hardening

### 1. Server Security

#### Firewall Configuration

```bash
# UFW configuration
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw --force enable
```

#### SSH Hardening

```bash
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
PermitEmptyPasswords no
ChallengeResponseAuthentication no
UsePAM yes
X11Forwarding no
PrintMotd no
AcceptEnv LANG LC_*
Subsystem sftp /usr/lib/openssh/sftp-server
```

### 2. Application Security

#### Security Headers

```nginx
# /etc/nginx/sites-available/n8n-clone
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

#### Rate Limiting

```php
// config/rate_limiting.php
return [
    'api' => [
        'throttle:api',
        Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()),
    ],
    'webhooks' => [
        'throttle:webhooks',
        Limit::perMinute(100)->by($request->ip()),
    ],
    'auth' => [
        'throttle:auth',
        Limit::perMinute(10)->by($request->ip()),
    ],
];
```

---

**🚀 Your n8n clone is now production-ready with enterprise-grade deployment, monitoring, and security features!**
