<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExecutionResource;
use App\Models\Execution;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExecutionController extends Controller
{
    /**
     * Get all executions for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Execution::with(['workflow', 'organization', 'user'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('organization', function ($orgQuery) use ($user) {
                      $orgQuery->whereHas('users', function ($userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id);
                      });
                  })
                  ->orWhereHas('workflow.team', function ($teamQuery) use ($user) {
                      $teamQuery->whereHas('users', function ($userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id);
                      });
                  });
            });

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('workflow_id')) {
            $query->where('workflow_id', $request->workflow_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('mode')) {
            $query->where('mode', $request->mode);
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query->orderBy($sortBy, $sortDirection);

        $executions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => ExecutionResource::collection($executions),
            'meta' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ]);
    }

    /**
     * Get a specific execution
     */
    public function show(Execution $execution): JsonResponse
    {
        // Check if user has access to this execution
        $user = auth()->user();

        if ($execution->user_id !== $user->id &&
            (!$execution->organization || !$execution->organization->isMember($user)) &&
            (!$execution->workflow->team || !$execution->workflow->team->isMember($user))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this execution',
            ], 403);
        }

        $execution->load(['workflow', 'organization', 'user', 'logs']);

        return response()->json([
            'success' => true,
            'data' => new ExecutionResource($execution),
        ]);
    }

    /**
     * Cancel a running execution
     */
    public function cancel(Execution $execution): JsonResponse
    {
        // Check if user has permission to cancel this execution
        $user = auth()->user();

        if ($execution->user_id !== $user->id &&
            (!$execution->organization || !$execution->organization->isAdmin($user)) &&
            (!$execution->workflow->team || !$execution->workflow->team->isAdmin($user))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to cancel this execution',
            ], 403);
        }

        if (!$execution->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Execution is not currently running',
            ], 422);
        }

        $execution->markAsCanceled();

        return response()->json([
            'success' => true,
            'message' => 'Execution cancelled successfully',
            'data' => new ExecutionResource($execution),
        ]);
    }

    /**
     * Retry a failed execution
     */
    public function retry(Execution $execution): JsonResponse
    {
        // Check if user has permission to retry this execution
        $user = auth()->user();

        if ($execution->user_id !== $user->id &&
            (!$execution->organization || !$execution->organization->isAdmin($user)) &&
            (!$execution->workflow->team || !$execution->workflow->team->isAdmin($user))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to retry this execution',
            ], 403);
        }

        if (!$execution->canBeRetried()) {
            return response()->json([
                'success' => false,
                'message' => 'Execution cannot be retried',
            ], 422);
        }

        $success = $execution->retry();

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry execution',
            ], 500);
        }

        $newExecution = Execution::where('workflow_id', $execution->workflow_id)
            ->where('retry_count', $execution->retry_count + 1)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Execution retry queued successfully',
            'data' => $newExecution ? new ExecutionResource($newExecution) : null,
        ]);
    }

    /**
     * Get execution logs
     */
    public function logs(Execution $execution): JsonResponse
    {
        // Check if user has access to this execution
        $user = auth()->user();

        if ($execution->user_id !== $user->id &&
            (!$execution->organization || !$execution->organization->isMember($user)) &&
            (!$execution->workflow->team || !$execution->workflow->team->isMember($user))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this execution',
            ], 403);
        }

        $logs = $execution->logs()
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get execution statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Execution::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('organization', function ($orgQuery) use ($user) {
                  $orgQuery->whereHas('users', function ($userQuery) use ($user) {
                      $userQuery->where('users.id', $user->id);
                  });
              });
        });

        // Apply date filter
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $query->whereBetween('created_at', [$dateFrom, $dateTo]);

        $stats = [
            'total_executions' => $query->count(),
            'successful_executions' => (clone $query)->successful()->count(),
            'failed_executions' => (clone $query)->failed()->count(),
            'running_executions' => (clone $query)->running()->count(),
            'average_execution_time' => (clone $query)->successful()->avg('duration'),
            'total_execution_time' => (clone $query)->successful()->sum('duration'),
            'success_rate' => $query->count() > 0
                ? round(((clone $query)->successful()->count() / $query->count()) * 100, 2)
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Bulk cancel executions
     */
    public function bulkCancel(Request $request): JsonResponse
    {
        $request->validate([
            'execution_ids' => 'required|array',
            'execution_ids.*' => 'integer|exists:executions,id',
        ]);

        $user = $request->user();
        $executionIds = $request->execution_ids;

        $executions = Execution::whereIn('id', $executionIds)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('organization', function ($orgQuery) use ($user) {
                      $orgQuery->whereHas('users', function ($userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id);
                      });
                  });
            })
            ->running()
            ->get();

        $cancelledCount = 0;
        foreach ($executions as $execution) {
            $execution->markAsCanceled();
            $cancelledCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Cancelled {$cancelledCount} executions",
            'data' => [
                'cancelled_count' => $cancelledCount,
            ],
        ]);
    }

    /**
     * Bulk delete executions
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'execution_ids' => 'required|array',
            'execution_ids.*' => 'integer|exists:executions,id',
        ]);

        $user = $request->user();
        $executionIds = $request->execution_ids;

        $executions = Execution::whereIn('id', $executionIds)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('organization', function ($orgQuery) use ($user) {
                      $orgQuery->whereHas('users', function ($userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id);
                      });
                  });
            })
            ->get();

        $deletedCount = $executions->count();
        foreach ($executions as $execution) {
            $execution->delete();
        }

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deletedCount} executions",
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ]);
    }

    /**
     * Get executions for a specific workflow
     */
    public function workflowExecutions(Request $request, Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $query = $workflow->executions()->with(['user']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('mode')) {
            $query->where('mode', $request->mode);
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query->orderBy($sortBy, $sortDirection);

        $executions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => ExecutionResource::collection($executions),
            'meta' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ]);
    }
}
