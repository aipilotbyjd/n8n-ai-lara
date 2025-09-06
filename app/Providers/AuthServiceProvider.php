<?php

namespace App\Providers;

use App\Models\Credential;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Workflow;
use App\Policies\CredentialPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\TeamPolicy;
use App\Policies\WorkflowPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Organization::class => OrganizationPolicy::class,
        Team::class => TeamPolicy::class,
        Workflow::class => WorkflowPolicy::class,
        Credential::class => CredentialPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Additional authorization gates can be defined here
        \Gate::before(function ($user, $ability) {
            // Super admin check - you can implement this later
            // return $user->hasRole('super-admin') ? true : null;
        });
    }
}
