<?php

namespace App\Console\Commands;

use App\Models\Credential;
use App\Models\Execution;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:seed-demo {--fresh : Drop all tables and re-run all migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed comprehensive demo data for the n8n clone';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Seeding n8n Clone Demo Data');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->warn('⚠️  This will drop all existing data!');
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('❌ Operation cancelled.');
                return;
            }

            $this->info('🔄 Dropping all tables and re-running migrations...');
            Artisan::call('migrate:fresh', [], $this->getOutput());
            $this->newLine();
        }

        $this->info('📦 Seeding demo data...');

        // Seed the database
        Artisan::call('db:seed', [], $this->getOutput());

        $this->newLine();
        $this->info('✅ Demo data seeded successfully!');
        $this->newLine();

        // Show statistics
        $this->displayStatistics();

        // Show login information
        $this->displayLoginInfo();

        $this->newLine();
        $this->info('🎉 Your n8n clone is ready to use!');
        $this->info('📖 Check README_SEEDERS.md for detailed API usage examples');
    }

    private function displayStatistics(): void
    {
        $this->info('📊 Database Statistics:');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Users', User::count()],
                ['Organizations', Organization::count()],
                ['Teams', Team::count()],
                ['Workflows', Workflow::count()],
                ['Executions', Execution::count()],
                ['Credentials', Credential::count()],
            ]
        );
        $this->newLine();
    }

    private function displayLoginInfo(): void
    {
        $this->info('🔐 Login Credentials:');
        $this->table(
            ['Email', 'Password', 'Organization', 'Role'],
            [
                ['john@example.com', 'password', 'Acme Corporation (Pro)', 'Admin'],
                ['jane@example.com', 'password', 'TechStart Inc (Free)', 'Admin'],
                ['bob@example.com', 'password', 'Acme Corporation (Pro)', 'Member'],
                ['alice@example.com', 'password', 'TechStart Inc (Free)', 'Member'],
                ['charlie@example.com', 'password', 'None', 'User'],
            ]
        );
        $this->newLine();

        $this->info('🌐 API Endpoints:');
        $this->line('  • Authentication: POST /api/auth/login');
        $this->line('  • Workflows: GET /api/workflows');
        $this->line('  • Execute Workflow: POST /api/workflows/{id}/execute');
        $this->line('  • Webhook Trigger: POST /api/webhooks/{workflowId}');
        $this->line('  • Node Manifest: GET /api/nodes/manifest');
        $this->newLine();

        $this->info('🧪 Sample Workflows Created:');
        $workflows = Workflow::whereNotNull('organization_id')->get();
        foreach ($workflows as $workflow) {
            $this->line("  • {$workflow->name} (ID: {$workflow->id})");
        }
        $this->newLine();

        $this->info('🔑 Sample Credentials Available:');
        $credentials = Credential::all();
        foreach ($credentials as $credential) {
            $this->line("  • {$credential->name} ({$credential->type})");
        }
    }
}
