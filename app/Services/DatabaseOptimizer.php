<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseOptimizer
{
    /**
     * Create optimized indexes for better query performance
     */
    public function createOptimizedIndexes(): void
    {
        $this->createWorkflowIndexes();
        $this->createExecutionIndexes();
        $this->createUserIndexes();
        $this->createOrganizationIndexes();
    }

    /**
     * Create optimized indexes for workflows table
     */
    private function createWorkflowIndexes(): void
    {
        Schema::table('workflows', function ($table) {
            // Composite indexes for common query patterns
            $table->index(['organization_id', 'user_id'], 'workflows_org_user_idx');
            $table->index(['status', 'is_active'], 'workflows_status_active_idx');
            $table->index(['created_at'], 'workflows_created_at_idx');
            $table->index(['updated_at'], 'workflows_updated_at_idx');

            // Partial indexes for better performance
            $table->index(['organization_id'], 'workflows_org_idx')
                  ->where('is_active', true);
            $table->index(['user_id'], 'workflows_user_active_idx')
                  ->where('is_active', true);
        });
    }

    /**
     * Create optimized indexes for executions table
     */
    private function createExecutionIndexes(): void
    {
        Schema::table('executions', function ($table) {
            // Composite indexes for execution queries
            $table->index(['workflow_id', 'status'], 'executions_workflow_status_idx');
            $table->index(['user_id', 'created_at'], 'executions_user_created_idx');
            $table->index(['organization_id', 'started_at'], 'executions_org_started_idx');

            // Performance indexes
            $table->index(['started_at'], 'executions_started_at_idx');
            $table->index(['finished_at'], 'executions_finished_at_idx');
            $table->index(['duration'], 'executions_duration_idx')
                  ->whereNotNull('finished_at');
        });
    }

    /**
     * Create optimized indexes for users table
     */
    private function createUserIndexes(): void
    {
        Schema::table('users', function ($table) {
            $table->index(['current_organization_id'], 'users_current_org_idx');
            $table->index(['email'], 'users_email_idx');
            $table->index(['created_at'], 'users_created_at_idx');
        });
    }

    /**
     * Create optimized indexes for organizations table
     */
    private function createOrganizationIndexes(): void
    {
        Schema::table('organizations', function ($table) {
            $table->index(['owner_id'], 'organizations_owner_idx');
            $table->index(['created_at'], 'organizations_created_at_idx');
        });
    }

    /**
     * Optimize table structure for better performance
     */
    public function optimizeTableStructure(): void
    {
        $this->optimizeWorkflowsTable();
        $this->optimizeExecutionsTable();
        $this->optimizeJsonColumns();
    }

    /**
     * Optimize workflows table structure
     */
    private function optimizeWorkflowsTable(): void
    {
        // Convert workflow_data to JSON type if not already
        DB::statement('ALTER TABLE workflows MODIFY COLUMN workflow_data JSON');

        // Add compression for large JSON fields
        DB::statement('ALTER TABLE workflows ROW_FORMAT=COMPRESSED');

        // Optimize for read-heavy workload
        DB::statement('ALTER TABLE workflows ENGINE=InnoDB');
    }

    /**
     * Optimize executions table structure
     */
    private function optimizeExecutionsTable(): void
    {
        // Convert JSON fields to proper types
        DB::statement('ALTER TABLE executions MODIFY COLUMN input_data JSON');
        DB::statement('ALTER TABLE executions MODIFY COLUMN output_data JSON');

        // Add partitioning for large execution tables
        $this->createExecutionPartitioning();
    }

    /**
     * Create partitioning for executions table
     */
    private function createExecutionPartitioning(): void
    {
        // Partition by month for better query performance
        $partitionSql = "
            ALTER TABLE executions
            PARTITION BY RANGE (YEAR(started_at)) (
                PARTITION p2024 VALUES LESS THAN (2025),
                PARTITION p2025 VALUES LESS THAN (2026),
                PARTITION p2026 VALUES LESS THAN (2027),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ";

        try {
            DB::statement($partitionSql);
        } catch (\Exception $e) {
            // Partitioning might not be supported or table might already be partitioned
            \Illuminate\Support\Facades\Log::warning('Could not create partitions: ' . $e->getMessage());
        }
    }

    /**
     * Optimize JSON columns for better performance
     */
    private function optimizeJsonColumns(): void
    {
        // Create generated columns for commonly queried JSON fields
        $this->createJsonGeneratedColumns();
    }

    /**
     * Create generated columns for JSON fields
     */
    private function createJsonGeneratedColumns(): void
    {
        // Add generated columns for workflow metadata
        Schema::table('workflows', function ($table) {
            $table->string('workflow_name_generated')->storedAs('JSON_UNQUOTE(JSON_EXTRACT(workflow_data, "$.name"))')->nullable();
            $table->string('workflow_type_generated')->storedAs('JSON_UNQUOTE(JSON_EXTRACT(workflow_data, "$.type"))')->nullable();
        });

        // Add indexes on generated columns
        DB::statement('CREATE INDEX workflows_name_gen_idx ON workflows (workflow_name_generated)');
        DB::statement('CREATE INDEX workflows_type_gen_idx ON workflows (workflow_type_generated)');
    }

    /**
     * Optimize database configuration
     */
    public function optimizeDatabaseConfiguration(): void
    {
        // Set optimal MySQL/MariaDB configuration
        $optimizations = [
            'innodb_buffer_pool_size' => '1G',
            'innodb_log_file_size' => '256M',
            'innodb_flush_log_at_trx_commit' => '2',
            'innodb_thread_concurrency' => '16',
            'query_cache_size' => '256M',
            'query_cache_type' => 'ON',
            'max_connections' => '200',
            'table_open_cache' => '2000',
            'tmp_table_size' => '256M',
            'max_heap_table_size' => '256M',
        ];

        foreach ($optimizations as $key => $value) {
            try {
                DB::statement("SET GLOBAL {$key} = '{$value}'");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Could not set {$key}: " . $e->getMessage());
            }
        }
    }

    /**
     * Create materialized views for common queries
     */
    public function createMaterializedViews(): void
    {
        $this->createWorkflowStatsView();
        $this->createExecutionStatsView();
        $this->createUserActivityView();
    }

    /**
     * Create workflow statistics materialized view
     */
    private function createWorkflowStatsView(): void
    {
        $viewSql = "
            CREATE VIEW workflow_stats AS
            SELECT
                w.id,
                w.name,
                w.organization_id,
                w.user_id,
                w.status,
                COUNT(e.id) as total_executions,
                AVG(e.duration) as avg_execution_time,
                MAX(e.started_at) as last_execution,
                SUM(CASE WHEN e.status = 'success' THEN 1 ELSE 0 END) as successful_executions,
                SUM(CASE WHEN e.status = 'error' THEN 1 ELSE 0 END) as failed_executions
            FROM workflows w
            LEFT JOIN executions e ON w.id = e.workflow_id
            GROUP BY w.id, w.name, w.organization_id, w.user_id, w.status
        ";

        DB::statement('DROP VIEW IF EXISTS workflow_stats');
        DB::statement($viewSql);
    }

    /**
     * Create execution statistics materialized view
     */
    private function createExecutionStatsView(): void
    {
        $viewSql = "
            CREATE VIEW execution_stats AS
            SELECT
                DATE(started_at) as execution_date,
                workflow_id,
                COUNT(*) as executions_count,
                AVG(duration) as avg_duration,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count
            FROM executions
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(started_at), workflow_id
        ";

        DB::statement('DROP VIEW IF EXISTS execution_stats');
        DB::statement($viewSql);
    }

    /**
     * Create user activity materialized view
     */
    private function createUserActivityView(): void
    {
        $viewSql = "
            CREATE VIEW user_activity AS
            SELECT
                u.id,
                u.name,
                u.email,
                COUNT(DISTINCT w.id) as workflows_created,
                COUNT(DISTINCT e.id) as executions_triggered,
                MAX(e.started_at) as last_activity,
                AVG(e.duration) as avg_execution_time
            FROM users u
            LEFT JOIN workflows w ON u.id = w.user_id
            LEFT JOIN executions e ON u.id = e.user_id
            GROUP BY u.id, u.name, u.email
        ";

        DB::statement('DROP VIEW IF EXISTS user_activity');
        DB::statement($viewSql);
    }

    /**
     * Analyze and optimize query performance
     */
    public function analyzeQueryPerformance(): array
    {
        $slowQueries = $this->getSlowQueries();
        $missingIndexes = $this->identifyMissingIndexes();
        $unusedIndexes = $this->identifyUnusedIndexes();

        return [
            'slow_queries' => $slowQueries,
            'missing_indexes' => $missingIndexes,
            'unused_indexes' => $unusedIndexes,
            'recommendations' => $this->generateOptimizationRecommendations($slowQueries, $missingIndexes, $unusedIndexes),
        ];
    }

    /**
     * Get slow queries from performance schema
     */
    private function getSlowQueries(): array
    {
        try {
            return DB::select("
                SELECT
                    sql_text,
                    exec_count,
                    avg_timer_wait/1000000000 as avg_time_sec,
                    sum_timer_wait/1000000000 as total_time_sec
                FROM performance_schema.events_statements_summary_by_digest
                WHERE avg_timer_wait > 1000000000
                ORDER BY avg_timer_wait DESC
                LIMIT 10
            ");
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Identify missing indexes
     */
    private function identifyMissingIndexes(): array
    {
        // This would analyze query patterns and suggest missing indexes
        // For now, return common recommendations
        return [
            'workflows' => ['organization_id', 'status'],
            'executions' => ['workflow_id', 'user_id'],
            'users' => ['current_organization_id'],
        ];
    }

    /**
     * Identify unused indexes
     */
    private function identifyUnusedIndexes(): array
    {
        try {
            return DB::select("
                SELECT
                    table_name,
                    index_name,
                    cardinality,
                    pages,
                    filter_condition
                FROM information_schema.statistics s
                LEFT JOIN information_schema.table_io_waits_summary t ON s.table_name = t.object_name
                WHERE s.table_schema = DATABASE()
                AND t.count_read = 0
                AND s.index_name != 'PRIMARY'
            ");
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate optimization recommendations
     */
    private function generateOptimizationRecommendations(array $slowQueries, array $missingIndexes, array $unusedIndexes): array
    {
        $recommendations = [];

        if (!empty($slowQueries)) {
            $recommendations[] = "Consider optimizing " . count($slowQueries) . " slow queries";
        }

        if (!empty($missingIndexes)) {
            $recommendations[] = "Add indexes for frequently queried columns";
        }

        if (!empty($unusedIndexes)) {
            $recommendations[] = "Consider removing " . count($unusedIndexes) . " unused indexes";
        }

        return $recommendations;
    }

    /**
     * Run full database maintenance
     */
    public function runMaintenance(): array
    {
        $results = [];

        // Analyze tables
        DB::statement('ANALYZE TABLE workflows, executions, organizations, users');
        $results[] = 'Tables analyzed';

        // Optimize tables
        DB::statement('OPTIMIZE TABLE workflows, executions, organizations, users');
        $results[] = 'Tables optimized';

        // Clear query cache
        DB::statement('RESET QUERY CACHE');
        $results[] = 'Query cache cleared';

        return $results;
    }
}
