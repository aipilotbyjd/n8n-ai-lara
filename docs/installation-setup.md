# 🚀 Installation & Setup Guide

Complete installation and setup instructions for the n8n clone workflow automation platform.

## 📋 Prerequisites

### System Requirements

| Component | Minimum | Recommended | Notes |
|-----------|---------|-------------|-------|
| **PHP** | 8.2 | 8.3+ | Laravel 12 requirement |
| **Node.js** | 18.0 | 20.0+ | For frontend and execution engine |
| **Database** | MySQL 8.0 / PostgreSQL 13+ | PostgreSQL 15+ | SQLite for development |
| **Redis** | 6.0 | 7.0+ | For caching and queues |
| **Composer** | 2.0 | 2.5+ | PHP dependency manager |
| **NPM** | 8.0 | 9.0+ | Node package manager |
| **Git** | 2.30 | 2.40+ | Version control |

### Hardware Requirements

#### Development Environment
- **RAM**: 4GB minimum, 8GB recommended
- **CPU**: 2 cores minimum, 4 cores recommended
- **Storage**: 10GB free space
- **Network**: Stable internet connection

#### Production Environment
- **RAM**: 8GB minimum, 16GB recommended
- **CPU**: 4 cores minimum, 8 cores recommended
- **Storage**: 50GB SSD minimum, 100GB recommended
- **Network**: High-speed internet connection

## 🛠️ Installation Methods

### Method 1: Complete Setup (Recommended)

#### 1. Clone Repository
```bash
git clone https://github.com/your-username/n8n-ai-lara.git
cd n8n-ai-lara
```

#### 2. Install PHP Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Install Node.js Dependencies
```bash
npm install --production
npm run build
```

#### 4. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

#### 5. Database Setup
```bash
# Create database
php artisan migrate

# Seed with demo data (optional)
php artisan n8n:seed-demo
```

#### 6. Storage Setup
```bash
php artisan storage:link
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

#### 7. Queue Worker Setup (Optional)
```bash
# Install Supervisor or use PM2
sudo apt-get install supervisor

# Configure supervisor for queue workers
sudo nano /etc/supervisor/conf.d/n8n-worker.conf
```

### Method 2: Docker Setup (Quick Start)

#### Using Docker Compose
```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=production
      - DB_CONNECTION=mysql
      - DB_HOST=mysql
      - DB_DATABASE=n8n_clone
      - DB_USERNAME=root
      - DB_PASSWORD=password
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: n8n_clone
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7.0-alpine
    ports:
      - "6379:6379"

volumes:
  mysql_data:
```

```bash
# Run with Docker
docker-compose up -d
docker-compose exec app php artisan migrate
docker-compose exec app php artisan n8n:seed-demo
```

## ⚙️ Configuration

### Environment Variables

#### Application Configuration
```env
APP_NAME="n8n Clone"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error
```

#### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=n8n_clone
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# For PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=n8n_clone
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

#### Redis Configuration
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Redis Clusters (optional)
REDIS_CLUSTER_HOSTS=127.0.0.1:6379,127.0.0.1:6380
```

#### Queue Configuration
```env
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids

# For high-performance setups
QUEUE_CONNECTION=database
```

#### Mail Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@n8n-clone.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### File Storage Configuration
```env
FILESYSTEM_DISK=local

# For cloud storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

### Advanced Configuration

#### Performance Tuning
```env
# PHP Configuration (php.ini)
memory_limit=256M
max_execution_time=300
max_input_time=60
post_max_size=50M
upload_max_filesize=50M

# Laravel Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### Security Configuration
```env
# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com
SANCTUM_GUARD=web

# CORS Configuration
CORS_ALLOWED_ORIGINS=https://your-frontend-domain.com
CORS_ALLOWED_HEADERS=*
CORS_ALLOWED_METHODS=*

# Rate Limiting
THROTTLE_ATTEMPTS=60
THROTTLE_DECAY_MINUTES=1
```

## 🗄️ Database Setup

### MySQL Setup
```sql
-- Create database
CREATE DATABASE n8n_clone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'n8n_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON n8n_clone.* TO 'n8n_user'@'localhost';
FLUSH PRIVILEGES;
```

### PostgreSQL Setup
```sql
-- Create database
CREATE DATABASE n8n_clone;

-- Create user
CREATE USER n8n_user WITH ENCRYPTED PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE n8n_clone TO n8n_user;

-- Connect to database and create extensions
\c n8n_clone
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";
```

### SQLite Setup (Development Only)
```bash
# SQLite is automatically created
touch database/database.sqlite
chmod 664 database/database.sqlite
```

## 🔐 SSL/TLS Setup

### Let's Encrypt (Recommended)
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Manual SSL Certificate
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # ... rest of nginx config
}
```

## 🌐 Web Server Configuration

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/n8n-ai-lara/public;
    index index.php index.html index.htm;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Handle PHP files
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Handle static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # API routes
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;

        # Rate limiting
        limit_req zone=api burst=10 nodelay;
    }

    # Webhook routes (no auth required)
    location /api/webhooks/ {
        try_files $uri $uri/ /index.php?$query_string;
        limit_req zone=webhooks burst=20 nodelay;
    }

    # Handle everything else
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(storage|bootstrap)/ {
        deny all;
    }
}
```

### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/n8n-ai-lara/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/your-domain.crt
    SSLCertificateKeyFile /etc/ssl/private/your-domain.key

    <Directory /var/www/n8n-ai-lara/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"

    # Enable compression
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|ico)$ no-gzip dont-vary
</VirtualHost>
```

## 🔄 Queue Worker Setup

### Using Supervisor
```ini
# /etc/supervisor/conf.d/n8n-worker.conf
[program:n8n-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/n8n-ai-lara/artisan queue:work --sleep=3 --tries=3 --max-jobs=1000
directory=/var/www/n8n-ai-lara
autostart=true
autorestart=true
numprocs=4
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/n8n-ai-lara/storage/logs/worker.log
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start n8n-worker:*
```

### Using PM2
```json
// ecosystem.config.js
module.exports = {
  apps: [{
    name: 'n8n-worker',
    script: 'artisan',
    args: 'queue:work --sleep=3 --tries=3 --max-jobs=1000',
    instances: 4,
    exec_mode: 'fork',
    cwd: '/var/www/n8n-ai-lara',
    env: {
      APP_ENV: 'production'
    }
  }]
}
```

```bash
npm install -g pm2
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

## 🔍 Health Checks

### Application Health Check
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'healthy' : 'unhealthy',
            'redis' => Redis::ping() ? 'healthy' : 'unhealthy',
            'storage' => Storage::disk('local')->exists('test') ? 'healthy' : 'unhealthy',
        ]
    ]);
});
```

### Database Health Check
```php
// app/Console/Commands/HealthCheck.php
public function handle()
{
    $this->info('Running health checks...');

    // Database check
    try {
        DB::connection()->getPdo();
        $this->info('✅ Database: Healthy');
    } catch (Exception $e) {
        $this->error('❌ Database: Unhealthy - ' . $e->getMessage());
    }

    // Redis check
    try {
        Redis::ping();
        $this->info('✅ Redis: Healthy');
    } catch (Exception $e) {
        $this->error('❌ Redis: Unhealthy - ' . $e->getMessage());
    }

    // Queue check
    try {
        Queue::size('default');
        $this->info('✅ Queue: Healthy');
    } catch (Exception $e) {
        $this->error('❌ Queue: Unhealthy - ' . $e->getMessage());
    }
}
```

## 🚀 Post-Installation Steps

### 1. Verify Installation
```bash
# Test basic functionality
php artisan tinker
>>> User::count()
>>> Workflow::count()

# Test API endpoints
curl -X GET "http://localhost:8000/api/nodes/manifest" \
  -H "Accept: application/json"

# Test authentication
curl -X POST "http://localhost:8000/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password"}'
```

### 2. Performance Optimization
```bash
# Clear and optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Setup task scheduling
crontab -e
# Add: * * * * * cd /var/www/n8n-ai-lara && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Monitoring Setup
```bash
# Install monitoring tools
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

# Setup log rotation
sudo nano /etc/logrotate.d/n8n-clone
```

### 4. Backup Configuration
```bash
# Create backup script
nano /usr/local/bin/n8n-backup.sh
chmod +x /usr/local/bin/n8n-backup.sh

# Add to crontab
# 0 2 * * * /usr/local/bin/n8n-backup.sh
```

## 🔧 Troubleshooting

### Common Installation Issues

#### Composer Issues
```bash
# Clear composer cache
composer clear-cache

# Update composer
composer self-update

# Force reinstall
rm -rf vendor/
composer install
```

#### Permission Issues
```bash
# Fix storage permissions
sudo chown -R www-data:www-data /var/www/n8n-ai-lara/storage
sudo chown -R www-data:www-data /var/www/n8n-ai-lara/bootstrap/cache

# Fix file permissions
find /var/www/n8n-ai-lara -type f -exec chmod 644 {} \;
find /var/www/n8n-ai-lara -type d -exec chmod 755 {} \;
```

#### Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo()

# Check database credentials
php artisan config:show database
```

## 📞 Support

If you encounter issues during installation:

1. **Check the logs**: `tail -f storage/logs/laravel.log`
2. **Verify requirements**: `php artisan --version`
3. **Test individual components**: Database, Redis, Queue
4. **Review documentation**: Check this guide and API docs
5. **Community support**: Join our Discord or GitHub Discussions

---

**🎉 Installation complete! Your n8n clone is ready to automate!**
