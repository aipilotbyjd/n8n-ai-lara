<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /**
     * Get all organizations for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()->organizations()
            ->with(['owner', 'teams'])
            ->withCount(['users', 'teams', 'workflows'])
            ->get();

        // Also include organizations owned by the user
        $ownedOrganizations = $request->user()->ownedOrganizations()
            ->with(['owner', 'teams'])
            ->withCount(['users', 'teams', 'workflows'])
            ->get();

        $allOrganizations = $organizations->merge($ownedOrganizations)->unique('id');

        return response()->json([
            'organizations' => $allOrganizations->map(function ($org) use ($request) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'description' => $org->description,
                    'owner' => new UserResource($org->owner),
                    'role' => $request->user()->getOrganizationRole($org),
                    'is_active' => $org->is_active,
                    'subscription_status' => $org->subscription_status,
                    'subscription_plan' => $org->subscription_plan,
                    'trial_ends_at' => $org->trial_ends_at,
                    'members_count' => $org->members_count,
                    'teams_count' => $org->teams_count,
                    'workflows_count' => $org->workflows_count,
                    'has_active_subscription' => $org->hasActiveSubscription(),
                    'created_at' => $org->created_at,
                    'updated_at' => $org->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Create a new organization
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug
        while (Organization::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $organization = Organization::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'owner_id' => $request->user()->id,
            'trial_ends_at' => now()->addDays(14), // 14-day trial
        ]);

        // Add owner as admin member
        $organization->users()->attach($request->user()->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Organization created successfully',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'description' => $organization->description,
                'owner' => new UserResource($organization->owner),
                'role' => 'owner',
                'is_active' => $organization->is_active,
                'subscription_status' => $organization->subscription_status,
                'trial_ends_at' => $organization->trial_ends_at,
                'has_active_subscription' => $organization->hasActiveSubscription(),
                'created_at' => $organization->created_at,
            ],
        ], 201);
    }

    /**
     * Get a specific organization
     */
    public function show(Request $request, Organization $organization): JsonResponse
    {
        // Check if user has access to this organization
        if (!$organization->isMember($request->user())) {
            return response()->json([
                'message' => 'You do not have access to this organization',
            ], 403);
        }

        $organization->load(['owner', 'teams', 'users'])
            ->loadCount(['workflows', 'credentials', 'executions']);

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'description' => $organization->description,
                'owner' => new UserResource($organization->owner),
                'role' => $request->user()->getOrganizationRole($organization),
                'is_active' => $organization->is_active,
                'subscription_status' => $organization->subscription_status,
                'subscription_plan' => $organization->subscription_plan,
                'trial_ends_at' => $organization->trial_ends_at,
                'subscription_ends_at' => $organization->subscription_ends_at,
                'settings' => $organization->settings,
                'members' => $organization->users->map(function ($user) use ($organization) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->organizations()->where('organization_id', $organization->id)->first()?->pivot?->role ?? 'member',
                        'joined_at' => $user->organizations()->where('organization_id', $organization->id)->first()?->pivot?->joined_at,
                    ];
                }),
                'teams' => $organization->teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                        'description' => $team->description,
                        'color' => $team->color,
                        'members_count' => $team->getMembersCount(),
                        'workflows_count' => $team->getWorkflowsCount(),
                        'created_at' => $team->created_at,
                    ];
                }),
                'stats' => [
                    'members_count' => $organization->users()->count() + 1, // +1 for owner
                    'teams_count' => $organization->teams()->count(),
                    'workflows_count' => $organization->workflows_count,
                    'credentials_count' => $organization->credentials_count,
                    'executions_count' => $organization->executions_count,
                ],
                'has_active_subscription' => $organization->hasActiveSubscription(),
                'created_at' => $organization->created_at,
                'updated_at' => $organization->updated_at,
            ],
        ]);
    }

    /**
     * Update organization
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        // Check if user can manage this organization
        if (!$request->user()->canManageOrganization($organization)) {
            return response()->json([
                'message' => 'You do not have permission to update this organization',
            ], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['sometimes', 'array'],
        ]);

        if ($request->has('name')) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;

            // Ensure unique slug
            while (Organization::where('slug', $slug)->where('id', '!=', $organization->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $organization->slug = $slug;
        }

        $organization->update($request->only(['name', 'description', 'settings']));

        return response()->json([
            'message' => 'Organization updated successfully',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'description' => $organization->description,
                'settings' => $organization->settings,
                'updated_at' => $organization->updated_at,
            ],
        ]);
    }

    /**
     * Delete organization
     */
    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        // Only owner can delete organization
        if (!$organization->isOwner($request->user())) {
            return response()->json([
                'message' => 'Only the organization owner can delete the organization',
            ], 403);
        }

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully',
        ]);
    }

    /**
     * Add member to organization
     */
    public function addMember(Request $request, Organization $organization): JsonResponse
    {
        // Check if user can manage this organization
        if (!$request->user()->canManageOrganization($organization)) {
            return response()->json([
                'message' => 'You do not have permission to manage this organization',
            ], 403);
        }

        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in(['member', 'admin'])],
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if user is already a member
        if ($organization->isMember($user)) {
            return response()->json([
                'message' => 'User is already a member of this organization',
            ], 422);
        }

        $organization->users()->attach($user->id, [
            'role' => $request->role,
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Member added successfully',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
                'joined_at' => now(),
            ],
        ]);
    }

    /**
     * Update member role
     */
    public function updateMember(Request $request, Organization $organization, User $user): JsonResponse
    {
        // Check if user can manage this organization
        if (!$request->user()->canManageOrganization($organization)) {
            return response()->json([
                'message' => 'You do not have permission to manage this organization',
            ], 403);
        }

        $request->validate([
            'role' => ['required', Rule::in(['member', 'admin'])],
        ]);

        // Check if target user is a member
        if (!$organization->isMember($user)) {
            return response()->json([
                'message' => 'User is not a member of this organization',
            ], 404);
        }

        $organization->users()->updateExistingPivot($user->id, [
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'Member role updated successfully',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ]);
    }

    /**
     * Remove member from organization
     */
    public function removeMember(Request $request, Organization $organization, User $user): JsonResponse
    {
        // Check if user can manage this organization
        if (!$request->user()->canManageOrganization($organization)) {
            return response()->json([
                'message' => 'You do not have permission to manage this organization',
            ], 403);
        }

        // Cannot remove owner
        if ($organization->isOwner($user)) {
            return response()->json([
                'message' => 'Cannot remove the organization owner',
            ], 422);
        }

        // Check if target user is a member
        if (!$organization->isMember($user)) {
            return response()->json([
                'message' => 'User is not a member of this organization',
            ], 404);
        }

        $organization->users()->detach($user->id);

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }

    /**
     * Switch to organization context
     */
    public function switchTo(Request $request, Organization $organization): JsonResponse
    {
        // Check if user is a member
        if (!$organization->isMember($request->user())) {
            return response()->json([
                'message' => 'You are not a member of this organization',
            ], 403);
        }

        // Store current organization in session/user context
        // This would typically be handled by middleware or session management

        return response()->json([
            'message' => 'Switched to organization successfully',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $request->user()->getOrganizationRole($organization),
            ],
        ]);
    }
}
