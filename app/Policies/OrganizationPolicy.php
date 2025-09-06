<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any organizations.
     */
    public function viewAny(User $user): bool
    {
        // Users can view organizations they belong to or own
        return true;
    }

    /**
     * Determine whether the user can view the organization.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can create organizations.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create organizations
        return true;
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(User $user, Organization $organization): bool
    {
        // Only organization owner can delete
        return $organization->isOwner($user);
    }

    /**
     * Determine whether the user can manage organization members.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can invite members to organization.
     */
    public function inviteMembers(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can remove members from organization.
     */
    public function removeMembers(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can manage organization billing.
     */
    public function manageBilling(User $user, Organization $organization): bool
    {
        return $organization->isOwner($user) || $organization->isAdmin($user);
    }

    /**
     * Determine whether the user can view organization billing.
     */
    public function viewBilling(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can manage organization settings.
     */
    public function manageSettings(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can switch to this organization.
     */
    public function switchTo(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can view organization workflows.
     */
    public function viewWorkflows(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can create workflows in organization.
     */
    public function createWorkflows(User $user, Organization $organization): bool
    {
        return $organization->isMember($user) && $organization->hasActiveSubscription();
    }

    /**
     * Determine whether the user can manage organization workflows.
     */
    public function manageWorkflows(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can view organization credentials.
     */
    public function viewCredentials(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can create credentials in organization.
     */
    public function createCredentials(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can manage organization credentials.
     */
    public function manageCredentials(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can view organization executions.
     */
    public function viewExecutions(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can manage organization executions.
     */
    public function manageExecutions(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can view organization teams.
     */
    public function viewTeams(User $user, Organization $organization): bool
    {
        return $organization->isMember($user);
    }

    /**
     * Determine whether the user can create teams in organization.
     */
    public function createTeams(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }

    /**
     * Determine whether the user can manage organization teams.
     */
    public function manageTeams(User $user, Organization $organization): bool
    {
        return $user->canManageOrganization($organization);
    }
}
