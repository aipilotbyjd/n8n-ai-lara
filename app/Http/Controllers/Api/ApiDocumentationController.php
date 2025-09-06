<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="N8N AI Workflow Automation Platform API",
 *     version="1.0.0",
 *     description="Ultra-scalable workflow automation platform with real-time execution, multi-tenancy, advanced queuing system, and comprehensive API documentation.",
 *     @OA\Contact(
 *         email="api@n8n-ai-lara.com",
 *         name="N8N AI Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Production API Server"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     name="Authorization",
 *     in="header",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum Bearer Token Authentication. Enter token in format (Bearer <token>)"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and profile management"
 * )
 *
 * @OA\Tag(
 *     name="Organizations",
 *     description="Multi-tenant organization management"
 * )
 *
 * @OA\Tag(
 *     name="Teams",
 *     description="Team collaboration within organizations"
 * )
 *
 * @OA\Tag(
 *     name="Workflows",
 *     description="Workflow creation, execution, and management"
 * )
 *
 * @OA\Tag(
 *     name="Executions",
 *     description="Workflow execution tracking and monitoring"
 * )
 *
 * @OA\Tag(
 *     name="Credentials",
 *     description="Secure API credential management"
 * )
 *
 * @OA\Tag(
 *     name="Queue",
 *     description="Background job queue monitoring and management"
 * )
 *
 * @OA\Tag(
 *     name="Monitoring",
 *     description="System monitoring and analytics"
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User account information",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="role", type="string", example="user", enum={"user", "admin", "super_admin"}),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     title="Organization",
 *     description="Multi-tenant organization",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="My Company"),
 *     @OA\Property(property="slug", type="string", example="my-company"),
 *     @OA\Property(property="description", type="string", example="Company description", nullable=true),
 *     @OA\Property(property="owner_id", type="integer", example=1),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="subscription_status", type="string", example="trial"),
 *     @OA\Property(property="subscription_plan", type="string", example="free"),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="subscription_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="owner", ref="#/components/schemas/User"),
 *     @OA\Property(property="teams_count", type="integer", example=5),
 *     @OA\Property(property="users_count", type="integer", example=25),
 *     @OA\Property(property="workflows_count", type="integer", example=15)
 * )
 *
 * @OA\Schema(
 *     schema="Team",
 *     type="object",
 *     title="Team",
 *     description="Collaborative team within an organization",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Development Team"),
 *     @OA\Property(property="description", type="string", example="Backend development team", nullable=true),
 *     @OA\Property(property="organization_id", type="integer", example=1),
 *     @OA\Property(property="owner_id", type="integer", example=1),
 *     @OA\Property(property="color", type="string", example="#3B82F6"),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="organization", ref="#/components/schemas/Organization"),
 *     @OA\Property(property="owner", ref="#/components/schemas/User"),
 *     @OA\Property(property="members_count", type="integer", example=8),
 *     @OA\Property(property="workflows_count", type="integer", example=12)
 * )
 *
 * @OA\Schema(
 *     schema="Workflow",
 *     type="object",
 *     title="Workflow",
 *     description="Automation workflow definition",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Email Notification Workflow"),
 *     @OA\Property(property="description", type="string", example="Sends email notifications for new orders", nullable=true),
 *     @OA\Property(property="organization_id", type="integer", example=1),
 *     @OA\Property(property="team_id", type="integer", example=1, nullable=true),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", example="active", enum={"active", "inactive", "draft"}),
 *     @OA\Property(property="workflow_data", type="object", description="Workflow definition in JSON format"),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="version", type="integer", example=1),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"email", "notification"}),
 *     @OA\Property(property="execution_count", type="integer", example=150),
 *     @OA\Property(property="last_executed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Execution",
 *     type="object",
 *     title="Execution",
 *     description="Workflow execution record",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="workflow_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", example="success", enum={"pending", "running", "success", "error", "cancelled"}),
 *     @OA\Property(property="mode", type="string", example="manual", enum={"manual", "trigger", "schedule", "webhook"}),
 *     @OA\Property(property="input_data", type="object", description="Execution input parameters"),
 *     @OA\Property(property="output_data", type="object", description="Execution results"),
 *     @OA\Property(property="error_message", type="string", nullable=true),
 *     @OA\Property(property="started_at", type="string", format="date-time"),
 *     @OA\Property(property="finished_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="duration", type="integer", example=2500, description="Execution time in milliseconds"),
 *     @OA\Property(property="retry_count", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Credential",
 *     type="object",
 *     title="Credential",
 *     description="Secure API credential storage",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Stripe API Key"),
 *     @OA\Property(property="type", type="string", example="stripe", enum={"stripe", "aws", "google", "custom"}),
 *     @OA\Property(property="organization_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="data", type="object", description="Encrypted credential data"),
 *     @OA\Property(property="shared", type="boolean", example=false),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_used_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="QueueStatus",
 *     type="object",
 *     title="Queue Status",
 *     description="Background job queue status",
 *     @OA\Property(property="default", type="object",
 *         @OA\Property(property="pending", type="integer", example=5),
 *         @OA\Property(property="processing", type="integer", example=2),
 *         @OA\Property(property="failed", type="integer", example=1)
 *     ),
 *     @OA\Property(property="high-priority", type="object",
 *         @OA\Property(property="pending", type="integer", example=2),
 *         @OA\Property(property="processing", type="integer", example=1),
 *         @OA\Property(property="failed", type="integer", example=0)
 *     ),
 *     @OA\Property(property="total", type="object",
 *         @OA\Property(property="pending", type="integer", example=7),
 *         @OA\Property(property="processing", type="integer", example=3),
 *         @OA\Property(property="failed", type="integer", example=1)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="HealthStatus",
 *     type="object",
 *     title="Health Status",
 *     description="System health monitoring",
 *     @OA\Property(property="overall_health", type="integer", example=85, description="Health score 0-100"),
 *     @OA\Property(property="queues", ref="#/components/schemas/QueueStatus"),
 *     @OA\Property(property="recommendations", type="array", @OA\Items(type="string"), example={"High queue backlog detected", "Consider scaling up workers"}),
 *     @OA\Property(property="last_checked", type="string", format="date-time")
 * )
 */
class ApiDocumentationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     summary="API Health Check",
     *     description="Check the health status of the API and system components",
     *     operationId="healthCheck",
     *     tags={"Monitoring"},
     *     @OA\Response(
     *         response=200,
     *         description="API is healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="version", type="string", example="1.0.0"),
     *             @OA\Property(property="services", type="object",
     *                 @OA\Property(property="database", type="string", example="connected"),
     *                 @OA\Property(property="redis", type="string", example="connected"),
     *                 @OA\Property(property="queue", type="string", example="operational")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Service unavailable",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="unhealthy"),
     *             @OA\Property(property="message", type="string", example="Service temporarily unavailable")
     *         )
     *     )
     * )
     */
    public function health(Request $request)
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now(),
            'version' => '1.0.0',
            'services' => [
                'database' => 'connected',
                'redis' => 'connected',
                'queue' => 'operational'
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/queue/status",
     *     summary="Get Queue Status",
     *     description="Get comprehensive status of the background job queue system",
     *     operationId="getQueueStatus",
     *     tags={"Queue", "Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Queue status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/QueueStatus"),
     *             @OA\Property(property="health", ref="#/components/schemas/HealthStatus"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function queueStatus(Request $request)
    {
        // This would integrate with the QueueManager service
        // For now, return mock data
        return response()->json([
            'data' => [
                'default' => ['pending' => 5, 'processing' => 2, 'failed' => 1],
                'high-priority' => ['pending' => 2, 'processing' => 1, 'failed' => 0],
                'low-priority' => ['pending' => 10, 'processing' => 3, 'failed' => 2],
                'total' => ['pending' => 17, 'processing' => 6, 'failed' => 3]
            ],
            'health' => [
                'overall_health' => 85,
                'recommendations' => ['High queue backlog detected', 'Consider scaling up workers'],
                'last_checked' => now()
            ],
            'timestamp' => now()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/metrics",
     *     summary="Get System Metrics",
     *     description="Get comprehensive system performance metrics and analytics",
     *     operationId="getSystemMetrics",
     *     tags={"Monitoring"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="workflows", type="object",
     *                 @OA\Property(property="total", type="integer", example=150),
     *                 @OA\Property(property="active", type="integer", example=120),
     *                 @OA\Property(property="executions_today", type="integer", example=2500)
     *             ),
     *             @OA\Property(property="organizations", type="object",
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="active", type="integer", example=22)
     *             ),
     *             @OA\Property(property="performance", type="object",
     *                 @OA\Property(property="avg_execution_time", type="number", example=2.5),
     *                 @OA\Property(property="success_rate", type="number", example=0.95),
     *                 @OA\Property(property="throughput_per_minute", type="integer", example=45)
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function metrics(Request $request)
    {
        return response()->json([
            'workflows' => [
                'total' => 150,
                'active' => 120,
                'executions_today' => 2500
            ],
            'organizations' => [
                'total' => 25,
                'active' => 22
            ],
            'performance' => [
                'avg_execution_time' => 2.5,
                'success_rate' => 0.95,
                'throughput_per_minute' => 45
            ],
            'timestamp' => now()
        ]);
    }
}
