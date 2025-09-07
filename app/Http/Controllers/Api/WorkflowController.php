<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\Engine\WorkflowExecutionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkflowController extends Controller
{
    private WorkflowExecutionEngine $executionEngine;

    public function __construct(WorkflowExecutionEngine $executionEngine)
    {
        $this->executionEngine = $executionEngine;
    }

    /**
     * Get all workflows for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Optimized eager loading with selective fields
        $query = Workflow::with([
            'organization:id,name',
            'team:id,name,organization_id',
            'user:id,name,email',
            'executionsOptimized:id,workflow_id,status,duration,started_at,finished_at'
        ])->forUser($user);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->has('is_template')) {
            $query->where('is_template', $request->boolean('is_template'));
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);
            $query->whereJsonContains('tags', $tags);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query->orderBy($sortBy, $sortDirection);

        $workflows = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => WorkflowResource::collection($workflows),
            'meta' => [
                'current_page' => $workflows->currentPage(),
                'last_page' => $workflows->lastPage(),
                'per_page' => $workflows->perPage(),
                'total' => $workflows->total(),
            ],
        ]);
    }

    /**
     * Create a new workflow
     */
    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Set default organization if not provided
        if (!isset($data['organization_id']) && $user->current_organization_id) {
            $data['organization_id'] = $user->current_organization_id;
        }

        // Ensure organization_id is set
        if (!isset($data['organization_id'])) {
            if ($user->current_organization_id) {
                $data['organization_id'] = $user->current_organization_id;
            } else {
                // Find the first organization the user belongs to
                $userOrganization = $user->organizations()->first();
                if ($userOrganization) {
                    $data['organization_id'] = $userOrganization->id;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User must belong to an organization to create workflows',
                    ], 422);
                }
            }
        }

        // Validate permissions
        $organization = Organization::find($data['organization_id']);
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }

        Gate::authorize('create', $organization);

        if (isset($data['team_id'])) {
            $team = Team::find($data['team_id']);
            if ($team && $team->organization_id !== $organization->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team does not belong to the selected organization',
                ], 422);
            }
            Gate::authorize('create', $team);
        }

        $data['user_id'] = $user->id;

        $workflow = Workflow::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Workflow created successfully',
            'data' => new WorkflowResource($workflow->load(['organization', 'team', 'user'])),
        ], 201);
    }

    /**
     * Get a specific workflow
     */
    public function show(Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $workflow->load(['organization', 'team', 'user', 'executions' => function ($query) {
            $query->latest()->take(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => new WorkflowResource($workflow),
        ]);
    }

    /**
     * Update a workflow
     */
    public function update(UpdateWorkflowRequest $request, Workflow $workflow): JsonResponse
    {
        Gate::authorize('update', $workflow);

        $data = $request->validated();

        // Validate organization/team changes
        if (isset($data['organization_id']) && $data['organization_id'] !== $workflow->organization_id) {
            $organization = Organization::find($data['organization_id']);
            Gate::authorize('create', $organization);
        }

        if (isset($data['team_id']) && $data['team_id'] !== $workflow->team_id) {
            $team = Team::find($data['team_id']);
            Gate::authorize('create', $team);
        }

        $workflow->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Workflow updated successfully',
            'data' => new WorkflowResource($workflow->fresh(['organization', 'team', 'user'])),
        ]);
    }

    /**
     * Delete a workflow
     */
    public function destroy(Workflow $workflow): JsonResponse
    {
        Gate::authorize('delete', $workflow);

        $workflow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Workflow deleted successfully',
        ]);
    }

    /**
     * Execute a workflow
     */
    public function execute(Request $request, Workflow $workflow): JsonResponse
    {
        Gate::authorize('execute', $workflow);

        // Validate workflow before execution
        $validation = $this->executionEngine->validateWorkflow($workflow);

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow validation failed',
                'errors' => $validation['errors'],
            ], 422);
        }

        $triggerData = $request->get('data', []);

        try {
            if ($request->get('async', true)) {
                // Asynchronous execution
                $jobId = $this->executionEngine->dispatchWorkflowExecution(
                    $workflow,
                    $triggerData,
                    $request->get('priority', 'normal')
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Workflow execution queued successfully',
                    'data' => [
                        'job_id' => $jobId,
                        'workflow_id' => $workflow->id,
                    ],
                ]);
            } else {
                // Synchronous execution
                $result = $this->executionEngine->executeWorkflowSync($workflow, $triggerData);

                return response()->json([
                    'success' => $result->isSuccess(),
                    'message' => $result->isSuccess() ? 'Workflow executed successfully' : 'Workflow execution failed',
                    'data' => [
                        'result' => $result->getOutputData(),
                        'error' => $result->getErrorMessage(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow execution failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test execute a workflow (without saving execution record)
     */
    public function testExecute(Request $request, Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $testData = $request->get('data', []);

        try {
            $result = $this->executionEngine->executeTestWorkflow($workflow, $testData);

            return response()->json([
                'success' => $result->isSuccess(),
                'message' => $result->isSuccess() ? 'Test execution completed successfully' : 'Test execution failed',
                'data' => [
                    'result' => $result->getOutputData(),
                    'error' => $result->getErrorMessage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test execution failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate a workflow
     */
    public function duplicate(Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $user = auth()->user();

        $duplicatedWorkflow = Workflow::create([
            'name' => $workflow->name . ' (Copy)',
            'slug' => $workflow->slug . '-copy-' . time(),
            'description' => $workflow->description,
            'organization_id' => $workflow->organization_id,
            'team_id' => $workflow->team_id,
            'user_id' => $user->id,
            'workflow_data' => $workflow->workflow_data,
            'settings' => $workflow->settings,
            'status' => 'draft',
            'is_active' => false,
            'is_template' => false,
            'tags' => $workflow->tags,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workflow duplicated successfully',
            'data' => new WorkflowResource($duplicatedWorkflow),
        ], 201);
    }

    /**
     * Get workflow statistics
     */
    public function statistics(Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $stats = [
            'total_executions' => $workflow->executions()->count(),
            'successful_executions' => $workflow->executions()->successful()->count(),
            'failed_executions' => $workflow->executions()->failed()->count(),
            'last_execution_at' => $workflow->last_executed_at,
            'execution_count' => $workflow->execution_count,
            'nodes_count' => $workflow->getNodesCount(),
            'connections_count' => $workflow->getConnectionsCount(),
            'average_execution_time' => $workflow->executions()->successful()->avg('duration'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Export workflow
     */
    public function export(Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $exportData = [
            'name' => $workflow->name,
            'description' => $workflow->description,
            'workflow_data' => $workflow->workflow_data,
            'settings' => $workflow->settings,
            'tags' => $workflow->tags,
            'exported_at' => now()->toISOString(),
            'version' => '1.0',
        ];

        return response()->json([
            'success' => true,
            'data' => $exportData,
        ]);
    }

    /**
     * Handle webhook triggers (public endpoint)
     */
    public function webhookTrigger(Request $request, string $workflowId): JsonResponse
    {
        try {
            // Find workflow by ID or slug
            $workflow = Workflow::where('id', $workflowId)
                ->orWhere('slug', $workflowId)
                ->first();

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], 404);
            }

            // Check if workflow is active
            if (!$workflow->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow is not active',
                ], 403);
            }

            // Prepare webhook data
            $webhookData = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'query' => $request->query->all(),
                'body' => $request->getContent(),
                'timestamp' => now()->toISOString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            // Try to parse JSON body
            if ($request->isJson()) {
                $webhookData['body'] = $request->json()->all();
            } elseif ($request->isXmlHttpRequest() || str_contains($request->header('Content-Type', ''), 'application/json')) {
                $webhookData['body'] = json_decode($request->getContent(), true) ?? $request->getContent();
            }

            // Execute workflow with webhook data
            $result = $this->executionEngine->executeWorkflowSync($workflow, $webhookData);

            // Return response based on workflow settings
            $responseCode = $workflow->settings['webhook_response_code'] ?? 200;
            $responseBody = $workflow->settings['webhook_response_body'] ?? ['success' => true];

            if ($result->isSuccess()) {
                $responseBody = $result->getOutputData() ?: $responseBody;
            } else {
                $responseCode = 500;
                $responseBody = [
                    'success' => false,
                    'error' => $result->getErrorMessage(),
                ];
            }

            return response()->json($responseBody, $responseCode);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Webhook execution failed', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook execution failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import workflow
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'workflow_data' => 'required|array',
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $data = $request->all();

        // Ensure organization_id is set for import
        if (!isset($data['organization_id'])) {
            if ($user->current_organization_id) {
                $data['organization_id'] = $user->current_organization_id;
            } else {
                // Find the first organization the user belongs to
                $userOrganization = $user->organizations()->first();
                if ($userOrganization) {
                    $data['organization_id'] = $userOrganization->id;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User must belong to an organization to import workflows',
                    ], 422);
                }
            }
        }

        // Validate organization exists
        $organization = Organization::find($data['organization_id']);
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }

        Gate::authorize('create', $organization);

        $workflow = Workflow::create([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']) . '-' . time(),
            'description' => $data['description'] ?? null,
            'organization_id' => $data['organization_id'],
            'team_id' => $data['team_id'] ?? null,
            'user_id' => $user->id,
            'workflow_data' => $data['workflow_data'],
            'settings' => $data['settings'] ?? [],
            'status' => 'draft',
            'is_active' => false,
            'is_template' => false,
            'tags' => $data['tags'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workflow imported successfully',
            'data' => new WorkflowResource($workflow),
        ], 201);
    }
}
