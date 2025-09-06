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

class TeamController extends Controller
{
    /**
     * Get all teams for an organization
     */
    public function index(Request $request, Organization $organization): JsonResponse
    {
        // Check if user has access to this organization
        if (!$organization->isMember($request->user())) {
            return response()->json([
                'message' => 'You do not have access to this organization',
            ], 403);
        }

        $teams = $organization->teams()
            ->with(['owner'])
            ->withCount(['users', 'workflows'])
            ->get();

        return response()->json([
            'teams' => $teams->map(function ($team) use ($request) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'description' => $team->description,
                    'color' => $team->color,
                    'owner' => new UserResource($team->owner),
                    'role' => $request->user()->getTeamRole($team),
                    'is_active' => $team->is_active,
                    'members_count' => $team->members_count,
                    'workflows_count' => $team->workflows_count,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Create a new team
     */
    public function store(Request $request, Organization $organization): JsonResponse
    {
        // Check if user can manage this organization
        if (!$request->user()->canManageOrganization($organization)) {
            return response()->json([
                'message' => 'You do not have permission to create teams in this organization',
            ], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
        ]);

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug within organization
        while (Team::where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $team = Team::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'organization_id' => $organization->id,
            'owner_id' => $request->user()->id,
            'color' => $request->color ?? '#3B82F6',
        ]);

        // Add creator as team member
        $team->users()->attach($request->user()->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Team created successfully',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'color' => $team->color,
                'owner' => new UserResource($team->owner),
                'role' => 'admin',
                'is_active' => $team->is_active,
                'members_count' => $team->getMembersCount(),
                'workflows_count' => $team->getWorkflowsCount(),
                'created_at' => $team->created_at,
            ],
        ], 201);
    }

    /**
     * Get a specific team
     */
    public function show(Request $request, Organization $organization, Team $team): JsonResponse
    {
        // Check if team belongs to organization
        if ($team->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Team not found in this organization',
            ], 404);
        }

        // Check if user has access to this organization
        if (!$organization->isMember($request->user())) {
            return response()->json([
                'message' => 'You do not have access to this organization',
            ], 403);
        }

        $team->load(['owner', 'organization', 'users'])
            ->loadCount(['workflows']);

        return response()->json([
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'color' => $team->color,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ],
                'owner' => new UserResource($team->owner),
                'role' => $request->user()->getTeamRole($team),
                'is_active' => $team->is_active,
                'settings' => $team->settings,
                'members' => $team->users->map(function ($user) use ($team) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->teams()->where('team_id', $team->id)->first()?->pivot?->role ?? 'member',
                        'joined_at' => $user->teams()->where('team_id', $team->id)->first()?->pivot?->joined_at,
                    ];
                }),
                'stats' => [
                    'members_count' => $team->getMembersCount(),
                    'workflows_count' => $team->getWorkflowsCount(),
                ],
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ],
        ]);
    }

    /**
     * Update team
     */
    public function update(Request $request, Organization $organization, Team $team): JsonResponse
    {
        // Check if team belongs to organization
        if ($team->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Team not found in this organization',
            ], 404);
        }

        // Check if user can manage this team
        if (!$request->user()->canManageTeam($team)) {
            return response()->json([
                'message' => 'You do not have permission to update this team',
            ], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'settings' => ['sometimes', 'array'],
        ]);

        if ($request->has('name')) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;

            // Ensure unique slug within organization
            while (Team::where('organization_id', $organization->id)
                      ->where('slug', $slug)
                      ->where('id', '!=', $team->id)
                      ->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $team->slug = $slug;
        }

        $team->update($request->only(['name', 'description', 'color', 'settings']));

        return response()->json([
            'message' => 'Team updated successfully',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'color' => $team->color,
                'settings' => $team->settings,
                'updated_at' => $team->updated_at,
            ],
        ]);
    }

    /**
     * Delete team
     */
    public function destroy(Request $request, Organization $organization, Team $team): JsonResponse
    {
        // Check if team belongs to organization
        if ($team->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Team not found in this organization',
            ], 404);
        }

        // Check if user can manage this team
        if (!$request->user()->canManageTeam($team)) {
            return response()->json([
                'message' => 'You do not have permission to delete this team',
            ], 403);
        }

        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully',
        ]);
    }

    /**
     * Add member to team
     */
    public function addMember(Request $request, Organization $organization, Team $team): JsonResponse
    {
        // Check if team belongs to organization
        if ($team->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Team not found in this organization',
            ], 404);
        }

        // Check if user can manage this team
        if (!$request->user()->canManageTeam($team)) {
            return response()->json([
                'message' => 'You do not have permission to manage this team',
            ], 403);
        }

        $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['member', 'admin'])],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Check if user is a member of the organization
        if (!$organization->isMember($user)) {
            return response()->json([
                'message' => 'User must be a member of the organization first',
            ], 422);
        }

        // Check if user is already a team member
        if ($team->isMember($user)) {
            return response()->json([
                'message' => 'User is already a member of this team',
            ], 422);
        }

        $team->users()->attach($user->id, [
            'role' => $request->role,
            'joined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Member added to team successfully',
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
     * Update team member role
     */
    public function updateMember(Request $request, Organization $organization, Team $team, User $user): JsonResponse
    {
        // Check if team belongs to organization
        if ($team->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Team not found in this organization',
            ], 404);
        }

        // Check if user can manage this team
        if (!$request->user()->canManageTeam($team)) {
            return response()->json([
                'message' => 'You do not have permission to manage this team',
            ], 403);
        }

        $request->validate([
            'role' => ['required', Rule::in(['member', 'admin'])],
        ]);

        // Check if target user is a team member
        if (!$team->isMember($user)) {
            return response()->json([
                'message' => 'User is not a member of this team',
            ], 404);
        }

        $team->users()->updateExistingPivot($user->id, [
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'Team member role updated successfully',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ]);
    }

    /**
     * Remove member from team
     */
    public function removeMember(Request $request, Organization $organization, Team $team, User $user): JsonResponse
    {
        // Check if team belongs to organization
        if ($team->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Team not found in this organization',
            ], 404);
        }

        // Check if user can manage this team
        if (!$request->user()->canManageTeam($team)) {
            return response()->json([
                'message' => 'You do not have permission to manage this team',
            ], 403);
        }

        // Cannot remove team owner
        if ($team->isOwner($user)) {
            return response()->json([
                'message' => 'Cannot remove the team owner',
            ], 422);
        }

        // Check if target user is a team member
        if (!$team->isMember($user)) {
            return response()->json([
                'message' => 'User is not a member of this team',
            ], 404);
        }

        $team->users()->detach($user->id);

        return response()->json([
            'message' => 'Member removed from team successfully',
        ]);
    }
}
