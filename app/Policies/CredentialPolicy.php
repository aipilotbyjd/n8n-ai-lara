<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CredentialPolicy
{
    /**
     * Determine whether the user can view any credentials.
     */
    public function viewAny(User $user): bool
    {
        // Users can view credentials in organizations they belong to
        return true;
    }

    /**
     * Determine whether the user can view the credential.
     */
    public function view(User $user, Credential $credential): bool
    {
        return $credential->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can create credentials.
     */
    public function create(User $user): bool
    {
        // Users can create credentials in organizations they belong to
        return true;
    }

    /**
     * Determine whether the user can update the credential.
     */
    public function update(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can delete the credential.
     */
    public function delete(User $user, Credential $credential): bool
    {
        return $credential->canBeDeletedBy($user);
    }

    /**
     * Determine whether the user can test the credential.
     */
    public function test(User $user, Credential $credential): bool
    {
        return $credential->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can share the credential.
     */
    public function share(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user) && $credential->organization->hasActiveSubscription();
    }

    /**
     * Determine whether the user can duplicate the credential.
     */
    public function duplicate(User $user, Credential $credential): bool
    {
        return $credential->canBeViewedBy($user) && $credential->organization->hasActiveSubscription();
    }

    /**
     * Determine whether the user can export the credential.
     */
    public function export(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can import credentials.
     */
    public function import(User $user): bool
    {
        return true; // Any authenticated user can import credentials
    }

    /**
     * Determine whether the user can view credential usage statistics.
     */
    public function viewUsage(User $user, Credential $credential): bool
    {
        return $credential->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can manage credential sharing.
     */
    public function manageSharing(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can view credential audit logs.
     */
    public function viewAuditLogs(User $user, Credential $credential): bool
    {
        return $credential->canBeViewedBy($user) || $credential->organization->isAdmin($user);
    }

    /**
     * Determine whether the user can rotate the credential.
     */
    public function rotate(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can manage credential expiration.
     */
    public function manageExpiration(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can view credential security settings.
     */
    public function viewSecuritySettings(User $user, Credential $credential): bool
    {
        return $credential->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can manage credential security settings.
     */
    public function manageSecuritySettings(User $user, Credential $credential): bool
    {
        return $credential->canBeEditedBy($user) || $credential->organization->isAdmin($user);
    }
}
