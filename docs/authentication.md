# 🔐 Authentication & Authorization

Comprehensive guide to user authentication, authorization, and security in the n8n clone.

## 🎯 Authentication Overview

The n8n clone implements a robust multi-layer authentication system supporting multiple authentication methods and granular authorization controls.

## 🔑 Authentication Methods

### 1. Bearer Token Authentication

#### Login Process
```bash
curl -X POST "https://api.your-domain.com/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "current_organization_id": 1,
      "role": "admin"
    },
    "token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx",
    "token_type": "Bearer",
    "expires_at": "2024-01-16T10:30:00Z"
  }
}
```

#### Using Bearer Tokens
```bash
curl -X GET "https://api.your-domain.com/api/workflows" \
  -H "Authorization: Bearer 1|abc123def456ghi789jkl012mno345pqr678stu901vwx"
```

### 2. API Key Authentication

#### Creating API Keys
```bash
curl -X POST "https://api.your-domain.com/api/auth/api-keys" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My API Key",
    "permissions": ["workflows.read", "workflows.execute"],
    "expires_at": "2024-12-31T23:59:59Z"
  }'
```

#### Using API Keys
```bash
curl -X GET "https://api.your-domain.com/api/workflows" \
  -H "X-API-Key: ak_abc123def456ghi789jkl012mno345pqr678stu901vwx"
```

### 3. Webhook Authentication

#### HMAC Signature Verification
```php
// Webhook controller
public function handleWebhook(Request $request, $workflowId)
{
    $signature = $request->header('X-Webhook-Signature');
    $payload = $request->getContent();
    $secret = config('webhooks.secret');

    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    if (!hash_equals($signature, $expectedSignature)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    // Process webhook...
}
```

#### Sending Signed Webhooks
```bash
# Calculate signature
PAYLOAD='{"event":"user.created","user_id":123}'
SECRET='your-webhook-secret'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" -hex)

# Send webhook with signature
curl -X POST "https://api.your-domain.com/api/webhooks/1" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

## 👥 User Management

### User Registration

#### Public Registration
```bash
curl -X POST "https://api.your-domain.com/api/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure_password_123",
    "password_confirmation": "secure_password_123"
  }'
```

#### Admin User Creation
```php
// Using Artisan command
php artisan tinker

>>> User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => Hash::make('password'),
    'role' => 'admin',
    'email_verified_at' => now()
]);
```

### User Profile Management

#### Get User Profile
```bash
curl -X GET "https://api.your-domain.com/api/auth/profile" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Update Profile
```bash
curl -X PUT "https://api.your-domain.com/api/auth/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Smith",
    "email": "johnsmith@example.com"
  }'
```

#### Change Password
```bash
curl -X POST "https://api.your-domain.com/api/auth/change-password" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "old_password",
    "password": "new_secure_password",
    "password_confirmation": "new_secure_password"
  }'
```

## 🏢 Organization Management

### Creating Organizations

#### Create New Organization
```bash
curl -X POST "https://api.your-domain.com/api/organizations" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Company",
    "description": "Company description",
    "settings": {
      "timezone": "America/New_York",
      "language": "en",
      "theme": "light"
    }
  }'
```

#### Organization Settings
```json
{
  "timezone": "America/New_York",
  "language": "en",
  "theme": "light",
  "max_workflows": 100,
  "max_executions_per_month": 10000,
  "features": ["webhooks", "api", "scheduling"],
  "security": {
    "require_2fa": false,
    "session_timeout": 3600,
    "password_policy": "medium"
  }
}
```

### Organization Members

#### Add Member to Organization
```bash
curl -X POST "https://api.your-domain.com/api/organizations/1/members" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 2,
    "role": "member"
  }'
```

#### Update Member Role
```bash
curl -X PUT "https://api.your-domain.com/api/organizations/1/members/2" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "admin"
  }'
```

#### Remove Member
```bash
curl -X DELETE "https://api.your-domain.com/api/organizations/1/members/2" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Switching Organizations

#### Switch Current Organization
```bash
curl -X POST "https://api.your-domain.com/api/organizations/2/switch" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "message": "Organization switched successfully",
  "data": {
    "organization": {
      "id": 2,
      "name": "Tech Startup Inc",
      "current": true
    }
  }
}
```

## 👨‍👩‍👧‍👦 Team Management

### Creating Teams

```bash
curl -X POST "https://api.your-domain.com/api/organizations/1/teams" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Engineering Team",
    "description": "Core engineering team",
    "color": "#3B82F6",
    "settings": {
      "department": "engineering",
      "notification_channels": ["slack", "email"]
    }
  }'
```

### Team Members

#### Add Member to Team
```bash
curl -X POST "https://api.your-domain.com/api/organizations/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 3,
    "role": "member"
  }'
```

#### Update Team Member Role
```bash
curl -X PUT "https://api.your-domain.com/api/organizations/1/teams/1/members/3" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "admin"
  }'
```

## 🔐 Authorization System

### Role-Based Access Control (RBAC)

#### User Roles

| Role | Description | Permissions |
|------|-------------|-------------|
| **Super Admin** | System administrator | All permissions |
| **Admin** | Organization administrator | Organization management, user management |
| **Member** | Regular user | Basic workflow operations |
| **Viewer** | Read-only user | View workflows and executions |

#### Permission Matrix

```php
class PermissionMatrix
{
    private static array $permissions = [
        'super_admin' => [
            'users.*',
            'organizations.*',
            'workflows.*',
            'executions.*',
            'credentials.*',
            'teams.*',
            'system.*'
        ],
        'admin' => [
            'users.read',
            'users.create',
            'users.update',
            'organizations.read',
            'organizations.update',
            'workflows.*',
            'executions.*',
            'credentials.*',
            'teams.*'
        ],
        'member' => [
            'workflows.read',
            'workflows.create',
            'workflows.update',
            'workflows.execute',
            'executions.read',
            'credentials.read',
            'teams.read'
        ],
        'viewer' => [
            'workflows.read',
            'executions.read',
            'teams.read'
        ]
    ];

    public static function hasPermission(string $role, string $permission): bool
    {
        $userPermissions = self::$permissions[$role] ?? [];

        return in_array($permission, $userPermissions) ||
               in_array('*', $userPermissions) ||
               self::hasWildcardMatch($userPermissions, $permission);
    }

    private static function hasWildcardMatch(array $permissions, string $permission): bool
    {
        foreach ($permissions as $userPermission) {
            if (str_contains($userPermission, '*')) {
                $pattern = str_replace('*', '.*', $userPermission);
                if (preg_match("/^{$pattern}$/", $permission)) {
                    return true;
                }
            }
        }
        return false;
    }
}
```

### Resource-Level Permissions

#### Workflow Permissions
```php
class WorkflowPolicy
{
    public function view(User $user, Workflow $workflow): bool
    {
        // User owns the workflow
        if ($user->id === $workflow->user_id) {
            return true;
        }

        // User is in the same organization
        if ($user->current_organization_id === $workflow->organization_id) {
            return true;
        }

        // User is admin of the organization
        if ($this->isOrganizationAdmin($user, $workflow->organization_id)) {
            return true;
        }

        // User is in the team that owns the workflow
        if ($workflow->team_id && $this->isTeamMember($user, $workflow->team_id)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Workflow $workflow): bool
    {
        // Owner can always update
        if ($user->id === $workflow->user_id) {
            return true;
        }

        // Organization admins can update
        if ($this->isOrganizationAdmin($user, $workflow->organization_id)) {
            return true;
        }

        // Team admins can update team workflows
        if ($workflow->team_id && $this->isTeamAdmin($user, $workflow->team_id)) {
            return true;
        }

        return false;
    }

    public function execute(User $user, Workflow $workflow): bool
    {
        // Anyone with view permission can execute (with restrictions)
        return $this->view($user, $workflow);
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        // Only owner or organization admin can delete
        return $user->id === $workflow->user_id ||
               $this->isOrganizationAdmin($user, $workflow->organization_id);
    }
}
```

### API-Level Authorization

#### Middleware Implementation
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        if (!PermissionMatrix::hasPermission($user->role, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        // Check resource-level permissions for specific resources
        if ($request->route('workflow')) {
            $workflow = $request->route('workflow');
            $policy = new WorkflowPolicy();

            $method = $request->getMethod();
            $action = match ($method) {
                'GET' => 'view',
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => 'view'
            };

            if (!$policy->$action($user, $workflow)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this resource'
                ], 403);
            }
        }

        return $next($request);
    }
}
```

#### Route Protection
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    // Workflows
    Route::apiResource('workflows', WorkflowController::class)
        ->middleware('permission:workflows.read');

    Route::post('workflows/{workflow}/execute', [WorkflowController::class, 'execute'])
        ->middleware('permission:workflows.execute');

    // Organizations
    Route::apiResource('organizations', OrganizationController::class)
        ->middleware('permission:organizations.read');

    // Admin only routes
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
});
```

## 🔒 Security Features

### Password Security

#### Password Requirements
```php
class PasswordValidation
{
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return $errors;
    }
}
```

#### Password Reset
```bash
# Request password reset
curl -X POST "https://api.your-domain.com/api/auth/forgot-password" \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'

# Reset password with token
curl -X POST "https://api.your-domain.com/api/auth/reset-password" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "reset_token_here",
    "email": "user@example.com",
    "password": "new_password",
    "password_confirmation": "new_password"
  }'
```

### Session Management

#### Session Configuration
```php
// config/session.php
return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours
    'expire_on_close' => false,
    'encrypt' => true,
    'path' => '/',
    'domain' => env('SESSION_DOMAIN', null),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
];
```

#### Active Session Management
```bash
# Get active sessions
curl -X GET "https://api.your-domain.com/api/auth/sessions" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Logout from specific session
curl -X DELETE "https://api.your-domain.com/api/auth/sessions/123" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Logout from all sessions
curl -X POST "https://api.your-domain.com/api/auth/logout-all" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Two-Factor Authentication (2FA)

#### Enable 2FA
```bash
# Generate 2FA secret
curl -X POST "https://api.your-domain.com/api/auth/2fa/enable" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Response includes QR code URL and secret
{
  "success": true,
  "data": {
    "secret": "JBSWY3DPEHPK3PXP",
    "qr_code_url": "otpauth://totp/...",
    "recovery_codes": ["12345678", "87654321", ...]
  }
}
```

#### Verify 2FA Setup
```bash
curl -X POST "https://api.your-domain.com/api/auth/2fa/verify" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "123456"
  }'
```

#### Login with 2FA
```bash
# Step 1: Regular login
curl -X POST "https://api.your-domain.com/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Step 2: Verify 2FA code
curl -X POST "https://api.your-domain.com/api/auth/2fa/challenge" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "123456"
  }'
```

### API Rate Limiting

#### Rate Limit Configuration
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

#### Custom Rate Limiting
```php
class CustomThrottleRequests
{
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);

        $maxAttempts = $this->calculateMaxAttempts($request, $maxAttempts);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    private function calculateMaxAttempts(Request $request, int $default): int
    {
        $user = $request->user();

        // Premium users get higher limits
        if ($user && $user->isPremium()) {
            return $default * 2;
        }

        // Organization-based limits
        if ($user && $user->currentOrganization()) {
            return $user->currentOrganization()->api_limit ?? $default;
        }

        return $default;
    }
}
```

## 🔐 Credential Management

### Secure Credential Storage

#### Credential Encryption
```php
class CredentialManager
{
    public static function encryptData(array $data): string
    {
        $jsonData = json_encode($data);
        return Crypt::encryptString($jsonData);
    }

    public static function decryptData(string $encryptedData): array
    {
        $jsonData = Crypt::decryptString($encryptedData);
        return json_decode($jsonData, true);
    }

    public static function storeCredential(array $data, User $user, Organization $organization): Credential
    {
        return Credential::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'data' => self::encryptData($data['credentials']),
            'is_shared' => $data['is_shared'] ?? false,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public static function getDecryptedCredential(Credential $credential, User $user): ?array
    {
        // Check permissions
        if (!$credential->is_shared && $credential->user_id !== $user->id) {
            return null;
        }

        // Check organization access
        if ($credential->organization_id !== $user->current_organization_id) {
            return null;
        }

        // Check expiration
        if ($credential->expires_at && $credential->expires_at->isPast()) {
            return null;
        }

        return self::decryptData($credential->data);
    }
}
```

### Credential Types

#### OAuth2 Credentials
```json
{
  "client_id": "abc123",
  "client_secret": "secret456",
  "access_token": "token789",
  "refresh_token": "refresh_token_123",
  "expires_at": "2024-01-15T10:30:00Z",
  "scope": ["read", "write"],
  "token_url": "https://api.example.com/oauth/token"
}
```

#### API Key Credentials
```json
{
  "api_key": "sk_abc123def456",
  "api_secret": "secret_xyz789",
  "base_url": "https://api.example.com",
  "headers": {
    "Authorization": "Bearer sk_abc123def456",
    "X-API-Key": "sk_abc123def456"
  }
}
```

#### Basic Auth Credentials
```json
{
  "username": "api_user",
  "password": "secure_password",
  "base_url": "https://api.example.com",
  "realm": "API Access"
}
```

## 📊 Audit Logging

### Security Event Logging

```php
class SecurityAuditor
{
    public static function logEvent(string $event, array $context = []): void
    {
        \Log::channel('security')->info($event, array_merge($context, [
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
        ]));
    }

    public static function logLogin(User $user, bool $success): void
    {
        self::logEvent($success ? 'user.login.success' : 'user.login.failed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'success' => $success,
        ]);
    }

    public static function logPermissionCheck(User $user, string $permission, bool $granted): void
    {
        self::logEvent('permission.check', [
            'user_id' => $user->id,
            'permission' => $permission,
            'granted' => $granted,
        ]);
    }

    public static function logCredentialAccess(User $user, Credential $credential): void
    {
        self::logEvent('credential.access', [
            'user_id' => $user->id,
            'credential_id' => $credential->id,
            'credential_type' => $credential->type,
            'credential_name' => $credential->name,
        ]);
    }
}
```

### Audit Trail Queries

```sql
-- Recent security events
SELECT * FROM security_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;

-- Failed login attempts by IP
SELECT ip_address, COUNT(*) as attempts
FROM security_logs
WHERE event = 'user.login.failed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address
HAVING attempts > 5
ORDER BY attempts DESC;

-- Permission violations
SELECT user_id, permission, COUNT(*) as violations
FROM security_logs
WHERE event = 'permission.denied'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY user_id, permission
ORDER BY violations DESC;
```

## 🚨 Security Best Practices

### 1. Token Management
- Use short-lived access tokens (1-2 hours)
- Implement refresh token rotation
- Store tokens securely (HttpOnly cookies for web clients)
- Implement token revocation

### 2. Password Security
- Enforce strong password requirements
- Implement password history checks
- Use bcrypt with appropriate cost factor
- Enable password reset with secure tokens

### 3. API Security
- Implement rate limiting on all endpoints
- Use HTTPS for all API communications
- Validate all input data
- Implement proper CORS policies

### 4. Session Security
- Use secure session cookies
- Implement session timeout
- Track concurrent sessions
- Provide session management UI

### 5. Audit & Monitoring
- Log all security events
- Monitor for suspicious activities
- Implement alerting for security events
- Regular security audits

---

**🔐 This authentication and authorization system provides enterprise-grade security with flexible permission management and comprehensive audit logging.**
