<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Auth\Access\Response;

class WorkflowPolicy
{
    /**
     * Determine whether the user can view any workflows.
     */
    public function viewAny(User $user): bool
    {
        // Users can view workflows in organizations they belong to
        return true;
    }

    /**
     * Determine whether the user can view the workflow.
     */
    public function view(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can create workflows.
     */
    public function create(User $user): bool
    {
        // Users can create workflows in organizations they belong to
        return true;
    }

    /**
     * Determine whether the user can update the workflow.
     */
    public function update(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can delete the workflow.
     */
    public function delete(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeDeletedBy($user);
    }

    /**
     * Determine whether the user can execute the workflow.
     */
    public function execute(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeExecutedBy($user);
    }

    /**
     * Determine whether the user can duplicate the workflow.
     */
    public function duplicate(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeViewedBy($user) && $workflow->organization->hasActiveSubscription();
    }

    /**
     * Determine whether the user can export the workflow.
     */
    public function export(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can import workflows.
     */
    public function import(User $user): bool
    {
        return true; // Any authenticated user can import workflows
    }

    /**
     * Determine whether the user can manage workflow tags.
     */
    public function manageTags(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can view workflow executions.
     */
    public function viewExecutions(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can manage workflow executions.
     */
    public function manageExecutions(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user) || $workflow->organization->isAdmin($user);
    }

    /**
     * Determine whether the user can view workflow analytics.
     */
    public function viewAnalytics(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can share the workflow.
     */
    public function share(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can publish the workflow.
     */
    public function publish(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user) && $workflow->organization->hasActiveSubscription();
    }

    /**
     * Determine whether the user can archive the workflow.
     */
    public function archive(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can restore the workflow.
     */
    public function restore(User $user, Workflow $workflow): bool
    {
        return $workflow->canBeEditedBy($user);
    }
}
