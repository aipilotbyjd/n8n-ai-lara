<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExecutionController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected authentication routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/tokens', [AuthController::class, 'tokens']);
        Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);
    });

    // Organization routes
    Route::prefix('organizations')->group(function () {
        Route::get('/', [OrganizationController::class, 'index']);
        Route::post('/', [OrganizationController::class, 'store']);
        Route::get('/{organization}', [OrganizationController::class, 'show']);
        Route::put('/{organization}', [OrganizationController::class, 'update']);
        Route::delete('/{organization}', [OrganizationController::class, 'destroy']);
        Route::post('/{organization}/switch', [OrganizationController::class, 'switchTo']);

        // Organization members
        Route::post('/{organization}/members', [OrganizationController::class, 'addMember']);
        Route::put('/{organization}/members/{user}', [OrganizationController::class, 'updateMember']);
        Route::delete('/{organization}/members/{user}', [OrganizationController::class, 'removeMember']);

        // Team routes within organizations
        Route::prefix('{organization}/teams')->group(function () {
            Route::get('/', [TeamController::class, 'index']);
            Route::post('/', [TeamController::class, 'store']);
            Route::get('/{team}', [TeamController::class, 'show']);
            Route::put('/{team}', [TeamController::class, 'update']);
            Route::delete('/{team}', [TeamController::class, 'destroy']);

            // Team members
            Route::post('/{team}/members', [TeamController::class, 'addMember']);
            Route::put('/{team}/members/{user}', [TeamController::class, 'updateMember']);
            Route::delete('/{team}/members/{user}', [TeamController::class, 'removeMember']);
        });
    });

    // Workflow routes
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);
        Route::post('/', [WorkflowController::class, 'store']);
        Route::get('/{workflow}', [WorkflowController::class, 'show']);
        Route::put('/{workflow}', [WorkflowController::class, 'update']);
        Route::delete('/{workflow}', [WorkflowController::class, 'destroy']);

        // Workflow actions
        Route::post('/{workflow}/execute', [WorkflowController::class, 'execute']);
        Route::post('/{workflow}/test-execute', [WorkflowController::class, 'testExecute']);
        Route::post('/{workflow}/duplicate', [WorkflowController::class, 'duplicate']);
        Route::get('/{workflow}/statistics', [WorkflowController::class, 'statistics']);
        Route::get('/{workflow}/export', [WorkflowController::class, 'export']);
    });

    // Workflow import route
    Route::post('/workflows/import', [WorkflowController::class, 'import']);

    // Execution routes
    Route::prefix('executions')->group(function () {
        Route::get('/', [ExecutionController::class, 'index']);
        Route::get('/{execution}', [ExecutionController::class, 'show']);
        Route::post('/{execution}/cancel', [ExecutionController::class, 'cancel']);
        Route::post('/{execution}/retry', [ExecutionController::class, 'retry']);
        Route::get('/{execution}/logs', [ExecutionController::class, 'logs']);
        Route::get('/statistics', [ExecutionController::class, 'statistics']);
        Route::post('/bulk-cancel', [ExecutionController::class, 'bulkCancel']);
        Route::post('/bulk-delete', [ExecutionController::class, 'bulkDelete']);
    });

    // Workflow executions
    Route::get('/workflows/{workflow}/executions', [ExecutionController::class, 'workflowExecutions']);

    // Node routes
    Route::prefix('nodes')->group(function () {
        Route::get('/', [NodeController::class, 'index']);
        Route::get('/manifest', [NodeController::class, 'manifest']);
        Route::get('/categories', [NodeController::class, 'categories']);
        Route::get('/statistics', [NodeController::class, 'statistics']);
        Route::get('/search', [NodeController::class, 'search']);
        Route::post('/refresh-cache', [NodeController::class, 'refreshCache']);

        Route::get('/{nodeId}', [NodeController::class, 'show']);
        Route::get('/{nodeId}/recommendations', [NodeController::class, 'recommendations']);
        Route::post('/{nodeId}/validate-properties', [NodeController::class, 'validateProperties']);
    });

    // Node categories
    Route::get('/nodes/categories/{category}', [NodeController::class, 'category']);

});

// Webhook endpoints (public routes for external triggers - outside auth middleware)
Route::prefix('webhooks')->group(function () {
    Route::post('/{workflowId}', [WorkflowController::class, 'webhookTrigger']);
    Route::get('/{workflowId}', [WorkflowController::class, 'webhookTrigger']);
    Route::put('/{workflowId}', [WorkflowController::class, 'webhookTrigger']);
});

// Performance monitoring routes
Route::middleware('auth:sanctum')->prefix('performance')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\PerformanceDashboardController::class, 'dashboard']);
    Route::post('/optimize', [App\Http\Controllers\PerformanceDashboardController::class, 'optimize']);
    Route::delete('/metrics', [App\Http\Controllers\PerformanceDashboardController::class, 'clearMetrics']);
});

// Legacy user route (for backward compatibility)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
