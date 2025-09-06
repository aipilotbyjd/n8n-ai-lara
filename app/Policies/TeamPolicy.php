<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        // Users can view teams in organizations they belong to
        return true;
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $team->isMember($user) || $team->organization->isMember($user);
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        // Users can create teams in organizations they can manage
        return true;
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        // Only team owner or organization admin can delete
        return $team->isOwner($user) || $team->organization->isAdmin($user);
    }

    /**
     * Determine whether the user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can invite members to team.
     */
    public function inviteMembers(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can remove members from team.
     */
    public function removeMembers(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can view team workflows.
     */
    public function viewWorkflows(User $user, Team $team): bool
    {
        return $team->isMember($user) || $team->organization->isMember($user);
    }

    /**
     * Determine whether the user can create workflows in team.
     */
    public function createWorkflows(User $user, Team $team): bool
    {
        return $team->isMember($user) && $team->organization->hasActiveSubscription();
    }

    /**
     * Determine whether the user can manage team workflows.
     */
    public function manageWorkflows(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can view team executions.
     */
    public function viewExecutions(User $user, Team $team): bool
    {
        return $team->isMember($user) || $team->organization->isMember($user);
    }

    /**
     * Determine whether the user can manage team executions.
     */
    public function manageExecutions(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can manage team settings.
     */
    public function manageSettings(User $user, Team $team): bool
    {
        return $user->canManageTeam($team);
    }

    /**
     * Determine whether the user can assign team to workflows.
     */
    public function assignToWorkflows(User $user, Team $team): bool
    {
        return $team->organization->isMember($user);
    }
}
