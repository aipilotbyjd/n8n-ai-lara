# 🔧 Troubleshooting Guide

Comprehensive troubleshooting guide for the n8n clone workflow automation platform.

## 🚨 Quick Diagnosis

### System Health Check

```bash
# Check overall system health
curl -s http://localhost/health | jq .

# Check Laravel application status
php artisan --version

# Check database connectivity
php artisan tinker --execute="echo DB::connection()->getPdo() ? 'Connected' : 'Failed';"

# Check Redis connectivity
php artisan tinker --execute="echo Redis::ping() ? 'Connected' : 'Failed';"

# Check queue status
php artisan queue:status
```

### Log Analysis

```bash
# View recent Laravel logs
tail -f storage/logs/laravel.log

# View queue worker logs
tail -f storage/logs/worker.log

# View nginx error logs
tail -f /var/log/nginx/error.log

# Search for specific errors
grep -r "ERROR" storage/logs/
grep -r "Exception" storage/logs/
```

## 🔧 Common Issues & Solutions

### 1. Application Won't Start

#### Issue: Laravel application fails to start

**Symptoms:**
- 502 Bad Gateway error
- Application returns blank page
- PHP-FPM not responding

**Solutions:**

```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Check PHP configuration
php -c /etc/php/8.2/fpm/php.ini -l

# Verify file permissions
ls -la /var/www/n8n-clone/
sudo chown -R www-data:www-data /var/www/n8n-clone/
sudo chmod -R 755 /var/www/n8n-clone/storage/
sudo chmod -R 755 /var/www/n8n-clone/bootstrap/cache/
```

#### Issue: Composer dependencies missing

```bash
# Reinstall dependencies
composer install --no-dev --optimize-autoloader

# Clear composer cache
composer clear-cache

# Check for platform requirements
composer check-platform-reqs
```

#### Issue: Environment configuration missing

```bash
# Check if .env file exists
ls -la .env

# Copy from example
cp .env.example .env

# Generate application key
php artisan key:generate

# Clear configuration cache
php artisan config:clear
php artisan cache:clear
```

### 2. Database Connection Issues

#### Issue: Database connection refused

**Symptoms:**
- `SQLSTATE[HY000] [2002] Connection refused`
- Application shows database errors
- Migrations fail

**Solutions:**

```bash
# Check database service status
sudo systemctl status mysql
sudo systemctl status postgresql

# Verify database credentials
php artisan tinker --execute="
config('database.connections.mysql.host');
config('database.connections.mysql.database');
config('database.connections.mysql.username');
"

# Test database connection
php artisan tinker --execute="
try {
    DB::connection()->getPdo();
    echo 'Database connection successful';
} catch (Exception $e) {
    echo 'Database connection failed: ' . $e->getMessage();
}
"

# Check database server logs
# MySQL
tail -f /var/log/mysql/error.log

# PostgreSQL
tail -f /var/log/postgresql/postgresql-*.log
```

#### Issue: Database migrations failing

```bash
# Check migration status
php artisan migrate:status

# Reset and rerun migrations
php artisan migrate:fresh

# Run specific migration
php artisan migrate --path=database/migrations/2025_09_06_200007_create_workflows_table.php

# Force migration in production
php artisan migrate --force
```

### 3. Authentication Problems

#### Issue: Users can't login

**Symptoms:**
- Login returns 401 Unauthorized
- Password reset not working
- API returns authentication errors

**Solutions:**

```bash
# Check user exists and is verified
php artisan tinker --execute="
$user = App\Models\User::where('email', 'user@example.com')->first();
if ($user) {
    echo 'User found: ' . $user->name . PHP_EOL;
    echo 'Email verified: ' . ($user->email_verified_at ? 'Yes' : 'No') . PHP_EOL;
    echo 'Password hash: ' . $user->password . PHP_EOL;
} else {
    echo 'User not found';
}
"

# Manually verify user email
php artisan tinker --execute="
App\Models\User::where('email', 'user@example.com')->update([
    'email_verified_at' => now()
]);
"

# Check Sanctum configuration
php artisan config:show sanctum
```

#### Issue: API tokens not working

```bash
# List user tokens
php artisan tinker --execute="
$user = App\Models\User::where('email', 'user@example.com')->first();
if ($user) {
    $tokens = $user->tokens;
    foreach ($tokens as $token) {
        echo 'Token: ' . $token->name . ' - Created: ' . $token->created_at . PHP_EOL;
    }
}
"

# Create new token for user
php artisan tinker --execute="
$user = App\Models\User::where('email', 'user@example.com')->first();
$token = $user->createToken('debug-token');
echo 'New token: ' . $token->plainTextToken;
"

# Check token abilities
php artisan tinker --execute="
$token = Laravel\Sanctum\PersonalAccessToken::findToken('your-token-here');
if ($token) {
    echo 'Token abilities: ' . json_encode($token->abilities) . PHP_EOL;
    echo 'Token expires: ' . $token->expires_at . PHP_EOL;
}
"
```

### 4. Workflow Execution Issues

#### Issue: Workflows not executing

**Symptoms:**
- Workflow triggers not firing
- Manual execution fails
- Queue jobs stuck

**Solutions:**

```bash
# Check queue worker status
php artisan queue:status

# List failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:clear

# Restart queue workers
php artisan queue:restart

# Check Redis connectivity
php artisan tinker --execute="
try {
    Redis::ping();
    echo 'Redis connected';
} catch (Exception $e) {
    echo 'Redis connection failed: ' . $e->getMessage();
}
"
```

#### Issue: Node execution failing

```bash
# Check node registry
php artisan tinker --execute="
$registry = app(App\Nodes\Registry\NodeRegistry::class);
$nodes = $registry->all();
echo 'Registered nodes: ' . count($nodes) . PHP_EOL;
foreach ($nodes as $node) {
    echo '- ' . $node->getId() . ': ' . $node->getName() . PHP_EOL;
}
"

# Test specific node
php artisan tinker --execute="
$registry = app(App\Nodes\Registry\NodeRegistry::class);
$httpNode = $registry->get('httpRequest');
if ($httpNode) {
    $context = new App\Workflow\Execution\NodeExecutionContext(
        // ... create test context
    );
    $result = $httpNode->execute($context);
    echo 'Node execution result: ' . ($result->isSuccess() ? 'Success' : 'Failed') . PHP_EOL;
}
"
```

#### Issue: Webhook triggers not working

```bash
# Check webhook routes
php artisan route:list | grep webhook

# Test webhook endpoint
curl -X POST "http://localhost/api/webhooks/1" \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'

# Check webhook workflow configuration
php artisan tinker --execute="
$workflow = App\Models\Workflow::find(1);
if ($workflow) {
    echo 'Workflow data: ' . json_encode($workflow->workflow_data, JSON_PRETTY_PRINT) . PHP_EOL;
}
"
```

### 5. Performance Issues

#### Issue: Slow response times

**Symptoms:**
- API responses taking >2 seconds
- Workflow execution delays
- Database queries slow

**Solutions:**

```bash
# Check Laravel performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Clear application caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Check database performance
php artisan tinker --execute="
// Enable query logging
DB::enableQueryLog();

// Run a test query
$users = App\Models\User::take(10)->get();

// Show slow queries
$queries = DB::getQueryLog();
foreach ($queries as $query) {
    if ($query['time'] > 100) { // Queries taking > 100ms
        echo 'Slow query: ' . $query['query'] . ' - Time: ' . $query['time'] . 'ms' . PHP_EOL;
    }
}
"

# Check Redis performance
php artisan tinker --execute="
$start = microtime(true);
Redis::set('test_key', 'test_value');
$value = Redis::get('test_key');
$time = (microtime(true) - $start) * 1000;
echo 'Redis operation time: ' . $time . 'ms' . PHP_EOL;
"
```

#### Issue: Memory usage high

```bash
# Check PHP memory usage
php -r "echo 'Memory limit: ' . ini_get('memory_limit') . PHP_EOL;"

# Check current memory usage
php artisan tinker --execute="
echo 'Peak memory usage: ' . (memory_get_peak_usage(true) / 1024 / 1024) . ' MB' . PHP_EOL;
echo 'Current memory usage: ' . (memory_get_usage(true) / 1024 / 1024) . ' MB' . PHP_EOL;
"

# Clear OPcache
php artisan opcache:clear

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

#### Issue: High CPU usage

```bash
# Check system load
uptime
top -b -n1 | head -20

# Check PHP-FPM process count
ps aux | grep php-fpm | wc -l

# Check queue worker processes
ps aux | grep "queue:work" | wc -l

# Restart services if needed
sudo systemctl restart php8.2-fpm
sudo systemctl restart supervisor
```

### 6. File Upload Issues

#### Issue: File uploads failing

**Symptoms:**
- File upload returns error
- Large files not uploading
- Permission denied errors

**Solutions:**

```bash
# Check PHP upload settings
php -r "
echo 'Upload max filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'Post max size: ' . ini_get('post_max_size') . PHP_EOL;
echo 'Max execution time: ' . ini_get('max_execution_time') . PHP_EOL;
"

# Check file permissions
ls -la storage/app/
ls -la storage/logs/

# Fix permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/

# Check nginx upload settings
cat /etc/nginx/nginx.conf | grep client_max_body_size

# Update nginx config if needed
sudo nano /etc/nginx/nginx.conf
# Add: client_max_body_size 50M;
```

### 7. Email Issues

#### Issue: Emails not sending

**Symptoms:**
- Password reset emails not received
- Workflow notification emails failing
- Email logs show errors

**Solutions:**

```bash
# Check email configuration
php artisan config:show mail

# Test email sending
php artisan tinker --execute="
try {
    Mail::raw('Test email', function (\$message) {
        \$message->to('test@example.com')->subject('Test');
    });
    echo 'Email sent successfully';
} catch (Exception \$e) {
    echo 'Email failed: ' . \$e->getMessage();
}
"

# Check mail queue
php artisan queue:status

# Clear mail queue if stuck
php artisan queue:clear
```

### 8. Frontend Issues

#### Issue: JavaScript errors

**Symptoms:**
- Workflow canvas not loading
- Node configuration failing
- Browser console shows errors

**Solutions:**

```bash
# Check Node.js and NPM versions
node --version
npm --version

# Reinstall frontend dependencies
rm -rf node_modules/
npm install

# Rebuild assets
npm run build

# Check for JavaScript errors in logs
tail -f storage/logs/laravel.log | grep -i "javascript\|frontend"

# Clear browser cache and cookies
# Hard refresh: Ctrl+Shift+R (Chrome/Firefox)
```

#### Issue: CSS/styling issues

```bash
# Check if assets are compiled
ls -la public/build/
ls -la public/css/
ls -la public/js/

# Recompile assets
npm run build

# Clear Laravel view cache
php artisan view:clear

# Check for CSS compilation errors
npm run dev
```

## 🔍 Advanced Debugging

### 1. Database Query Analysis

```bash
# Enable query logging
php artisan tinker --execute="
DB::enableQueryLog();
// Run your code here
$queries = DB::getQueryLog();
foreach ($queries as $query) {
    echo 'Query: ' . $query['query'] . PHP_EOL;
    echo 'Time: ' . $query['time'] . 'ms' . PHP_EOL;
    echo 'Bindings: ' . json_encode($query['bindings']) . PHP_EOL;
    echo '---' . PHP_EOL;
}
"
```

### 2. Performance Profiling

```php
# Use Laravel Debugbar (development only)
composer require barryvdh/laravel-debugbar --dev
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"

# Profile specific methods
php artisan tinker --execute="
$start = microtime(true);
$result = app(App\Workflow\Engine\WorkflowExecutionEngine::class)->executeWorkflowSync(\$workflow);
$time = (microtime(true) - $start) * 1000;
echo 'Execution time: ' . $time . 'ms' . PHP_EOL;
echo 'Memory usage: ' . (memory_get_peak_usage(true) / 1024 / 1024) . ' MB' . PHP_EOL;
"
```

### 3. Network Debugging

```bash
# Check network connectivity
curl -I https://api.github.com
curl -I https://httpbin.org

# Test webhook endpoints
curl -X POST http://localhost/api/webhooks/1 \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}' \
  -v

# Check SSL certificates
openssl s_client -connect api.github.com:443 -servername api.github.com
```

### 4. Cache Debugging

```bash
# Check cache status
php artisan tinker --execute="
echo 'Cache driver: ' . config('cache.default') . PHP_EOL;
echo 'Cache working: ' . (Cache::store()->getStore()->connection()->ping() ? 'Yes' : 'No') . PHP_EOL;
"

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check Redis keys
php artisan tinker --execute="
$keys = Redis::keys('*');
echo 'Redis keys: ' . count($keys) . PHP_EOL;
foreach (array_slice($keys, 0, 10) as $key) {
    echo '- ' . $key . PHP_EOL;
}
"
```

## 📊 Monitoring & Alerts

### 1. Health Check Endpoints

```php
// routes/web.php
Route::get('/health', function () {
    $checks = [];

    // Database check
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'healthy';
    } catch (Exception $e) {
        $checks['database'] = 'unhealthy: ' . $e->getMessage();
    }

    // Redis check
    try {
        Redis::ping();
        $checks['redis'] = 'healthy';
    } catch (Exception $e) {
        $checks['redis'] = 'unhealthy: ' . $e->getMessage();
    }

    // Queue check
    try {
        Queue::size('default');
        $checks['queue'] = 'healthy';
    } catch (Exception $e) {
        $checks['queue'] = 'unhealthy: ' . $e->getMessage();
    }

    $status = collect($checks)->contains('unhealthy') ? 500 : 200;

    return response()->json([
        'status' => $status === 200 ? 'healthy' : 'unhealthy',
        'timestamp' => now(),
        'checks' => $checks
    ], $status);
});
```

### 2. Custom Monitoring

```php
// app/Console/Commands/MonitorSystem.php
class MonitorSystem extends Command
{
    protected $signature = 'monitor:system';
    protected $description = 'Monitor system health and performance';

    public function handle()
    {
        $this->info('🔍 System Health Check');
        $this->newLine();

        // Check database
        $this->checkDatabase();

        // Check Redis
        $this->checkRedis();

        // Check queues
        $this->checkQueues();

        // Check disk space
        $this->checkDiskSpace();

        // Check memory usage
        $this->checkMemoryUsage();

        $this->newLine();
        $this->info('✅ Health check completed');
    }

    private function checkDatabase()
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $time = (microtime(true) - $start) * 1000;

            $this->info("✅ Database: Connected ({$time}ms)");
        } catch (Exception $e) {
            $this->error("❌ Database: Failed - {$e->getMessage()}");
        }
    }

    private function checkRedis()
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $time = (microtime(true) - $start) * 1000;

            $this->info("✅ Redis: Connected ({$time}ms)");
        } catch (Exception $e) {
            $this->error("❌ Redis: Failed - {$e->getMessage()}");
        }
    }

    private function checkQueues()
    {
        try {
            $size = Queue::size('default');
            $this->info("✅ Queue: {$size} jobs pending");
        } catch (Exception $e) {
            $this->error("❌ Queue: Failed - {$e->getMessage()}");
        }
    }

    private function checkDiskSpace()
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $percentage = round(($free / $total) * 100, 1);

        if ($percentage < 10) {
            $this->error("❌ Disk: Only {$percentage}% free space");
        } else {
            $this->info("✅ Disk: {$percentage}% free space");
        }
    }

    private function checkMemoryUsage()
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();

        $usagePercent = round(($usage / $limit) * 100, 1);
        $peakPercent = round(($peak / $limit) * 100, 1);

        if ($usagePercent > 80) {
            $this->error("❌ Memory: {$usagePercent}% used ({$peakPercent}% peak)");
        } else {
            $this->info("✅ Memory: {$usagePercent}% used ({$peakPercent}% peak)");
        }
    }

    private function getMemoryLimit()
    {
        $limit = ini_get('memory_limit');
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
}
```

## 🚨 Emergency Procedures

### 1. Application Down

```bash
# Quick restart sequence
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart supervisor

# Check services
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status supervisor
```

### 2. Database Issues

```bash
# Check database status
sudo systemctl status mysql
sudo systemctl status postgresql

# Restart database
sudo systemctl restart mysql
sudo systemctl restart postgresql

# Check database logs
tail -f /var/log/mysql/error.log
tail -f /var/log/postgresql/postgresql-*.log
```

### 3. Queue Stuck

```bash
# Check queue worker status
ps aux | grep "queue:work"

# Kill stuck workers
pkill -f "queue:work"

# Restart queue workers
sudo supervisorctl restart all

# Clear stuck jobs
php artisan queue:clear
php artisan queue:failed
php artisan queue:retry all
```

### 4. High Load Issues

```bash
# Check system load
uptime
top -b -n1 | head -20

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
sudo systemctl restart supervisor

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan opcache:clear
```

## 📞 Getting Help

### 1. Log Collection

```bash
# Collect system information
php artisan --version
composer --version
node --version
npm --version

# Collect logs
tar -czf logs.tar.gz storage/logs/
tar -czf system-logs.tar.gz /var/log/nginx/ /var/log/supervisor/

# Database information
php artisan migrate:status
php artisan queue:status
```

### 2. Diagnostic Commands

```bash
# Full system diagnostic
curl -s http://localhost/health | jq .
php artisan about
php artisan config:show app
php artisan config:show database
php artisan config:show queue
```

### 3. Support Information

When reporting issues, please include:

- **System Information**: OS, PHP version, Database type
- **Error Logs**: Relevant log entries
- **Configuration**: Sanitized config files
- **Steps to Reproduce**: Detailed reproduction steps
- **Expected vs Actual**: What should happen vs what happens

## 🛠️ Recovery Procedures

### 1. Database Recovery

```bash
# Stop application
sudo systemctl stop supervisor
sudo systemctl stop nginx

# Restore from backup
gunzip < /path/to/backup.sql.gz | mysql -u root -p n8n_clone

# Start application
sudo systemctl start nginx
sudo systemctl start supervisor
```

### 2. Application Rollback

```bash
# Rollback to previous version
git log --oneline -10
git checkout <previous-commit-hash>
git submodule update

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
npm ci --production
npm run build

# Run migrations
php artisan migrate

# Restart services
sudo systemctl restart nginx
sudo systemctl restart supervisor
```

---

**🔧 This troubleshooting guide covers the most common issues and provides systematic approaches to diagnosis and resolution.**
