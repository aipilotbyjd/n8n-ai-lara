<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create sample plans
        $this->createPlans();

        // Create sample users
        $this->createUsers();

        // Create sample organizations
        $this->createOrganizations();

        // Create sample teams
        $this->createTeams();
    }

    private function createPlans(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for getting started with basic workflows',
                'price' => 0,
                'currency' => 'usd',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => 14,
                'features' => ['Basic workflows', 'Community support', 'Up to 5 workflows'],
                'limits' => [
                    'workflows' => 5,
                    'executions_per_month' => 1000,
                    'team_members' => 1,
                    'credentials' => 10,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Advanced features for growing teams',
                'price' => 29,
                'currency' => 'usd',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => 14,
                'features' => ['Advanced workflows', 'Priority support', 'Unlimited workflows', 'Team collaboration'],
                'limits' => [
                    'workflows' => -1, // unlimited
                    'executions_per_month' => 50000,
                    'team_members' => 10,
                    'credentials' => 100,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Full-featured platform for large organizations',
                'price' => 99,
                'currency' => 'usd',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => 30,
                'features' => ['All Pro features', 'Custom integrations', 'Advanced security', 'Dedicated support', 'SLA guarantee'],
                'limits' => [
                    'workflows' => -1, // unlimited
                    'executions_per_month' => -1, // unlimited
                    'team_members' => -1, // unlimited
                    'credentials' => -1, // unlimited
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }
    }

    private function createUsers(): void
    {
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ],
            [
                'name' => 'Alice Brown',
                'email' => 'alice@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ],
            [
                'name' => 'Charlie Wilson',
                'email' => 'charlie@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }

    private function createOrganizations(): void
    {
        $users = User::all();

        $organizations = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme-corporation',
                'description' => 'Leading technology company specializing in workflow automation',
                'owner_id' => $users->first()->id,
                'subscription_status' => 'active',
                'subscription_plan' => 'pro',
                'trial_ends_at' => now()->addDays(14),
                'settings' => [
                    'timezone' => 'America/New_York',
                    'language' => 'en',
                    'theme' => 'light',
                ],
            ],
            [
                'name' => 'TechStart Inc',
                'slug' => 'techstart-inc',
                'description' => 'Innovative startup building the future of automation',
                'owner_id' => $users->skip(1)->first()->id,
                'subscription_status' => 'trial',
                'subscription_plan' => 'free',
                'trial_ends_at' => now()->addDays(10),
                'settings' => [
                    'timezone' => 'Europe/London',
                    'language' => 'en',
                    'theme' => 'dark',
                ],
            ],
        ];

        foreach ($organizations as $orgData) {
            $organization = Organization::create($orgData);

            // Add owner as admin member
            $organization->users()->attach($orgData['owner_id'], [
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add some additional members
            $otherUsers = $users->where('id', '!=', $orgData['owner_id'])->take(2);
            foreach ($otherUsers as $user) {
                $organization->users()->attach($user->id, [
                    'role' => rand(0, 1) ? 'admin' : 'member',
                    'joined_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    private function createTeams(): void
    {
        $organizations = Organization::all();

        $teams = [
            [
                'name' => 'Engineering',
                'slug' => 'engineering',
                'description' => 'Core engineering team responsible for platform development',
                'color' => '#3B82F6',
                'settings' => ['department' => 'engineering'],
            ],
            [
                'name' => 'Product',
                'slug' => 'product',
                'description' => 'Product management and design team',
                'color' => '#10B981',
                'settings' => ['department' => 'product'],
            ],
            [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'description' => 'Marketing and growth team',
                'color' => '#F59E0B',
                'settings' => ['department' => 'marketing'],
            ],
            [
                'name' => 'DevOps',
                'slug' => 'devops',
                'description' => 'Infrastructure and deployment team',
                'color' => '#EF4444',
                'settings' => ['department' => 'devops'],
            ],
        ];

        foreach ($organizations as $organization) {
            $teamCount = rand(1, 3);
            $selectedTeams = array_slice($teams, 0, $teamCount);

            foreach ($selectedTeams as $teamData) {
                $team = Team::create([
                    'name' => $teamData['name'],
                    'slug' => $teamData['slug'],
                    'description' => $teamData['description'],
                    'organization_id' => $organization->id,
                    'owner_id' => $organization->owner_id,
                    'color' => $teamData['color'],
                    'settings' => $teamData['settings'],
                ]);

                // Add team owner
                $team->users()->attach($organization->owner_id, [
                    'role' => 'admin',
                    'joined_at' => now(),
                ]);

                // Add some team members from organization
                $orgMembers = $organization->users()->where('user_id', '!=', $organization->owner_id)->get();
                $memberCount = min(rand(1, 3), $orgMembers->count());

                foreach ($orgMembers->take($memberCount) as $member) {
                    $team->users()->attach($member->id, [
                        'role' => rand(0, 1) ? 'admin' : 'member',
                        'joined_at' => now()->subDays(rand(1, 20)),
                    ]);
                }
            }
        }
    }
}
