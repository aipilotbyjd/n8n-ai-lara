<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseQueryNode implements NodeInterface
{
    public function getId(): string
    {
        return 'databaseQuery';
    }

    public function getName(): string
    {
        return 'Database Query';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCategory(): string
    {
        return 'data';
    }

    public function getIcon(): string
    {
        return 'database';
    }

    public function getDescription(): string
    {
        return 'Execute database queries and return results';
    }

    public function getProperties(): array
    {
        return [
            'connection' => [
                'type' => 'select',
                'options' => ['mysql', 'pgsql', 'sqlite', 'sqlsrv'],
                'default' => 'mysql',
                'description' => 'Database connection to use',
            ],
            'query_type' => [
                'type' => 'select',
                'options' => ['select', 'insert', 'update', 'delete', 'raw'],
                'default' => 'select',
                'required' => true,
            ],
            'table' => [
                'type' => 'string',
                'placeholder' => 'users',
                'description' => 'Table name for the query',
                'condition' => 'query_type !== "raw"',
            ],
            'columns' => [
                'type' => 'string',
                'placeholder' => 'id, name, email',
                'description' => 'Columns to select (comma-separated)',
                'condition' => 'query_type === "select"',
            ],
            'where' => [
                'type' => 'object',
                'description' => 'Where conditions',
                'properties' => [
                    'column' => ['type' => 'string'],
                    'operator' => ['type' => 'select', 'options' => ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN']],
                    'value' => ['type' => 'string'],
                ],
            ],
            'order_by' => [
                'type' => 'string',
                'placeholder' => 'created_at DESC',
                'description' => 'ORDER BY clause',
                'condition' => 'query_type === "select"',
            ],
            'limit' => [
                'type' => 'number',
                'default' => 100,
                'min' => 1,
                'max' => 10000,
                'description' => 'Maximum number of records to return',
                'condition' => 'query_type === "select"',
            ],
            'raw_query' => [
                'type' => 'string',
                'description' => 'Raw SQL query',
                'condition' => 'query_type === "raw"',
            ],
            'parameters' => [
                'type' => 'object',
                'description' => 'Query parameters for prepared statements',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data for dynamic queries',
                'properties' => [
                    'table' => ['type' => 'string'],
                    'where' => ['type' => 'object'],
                    'parameters' => ['type' => 'object'],
                    'limit' => ['type' => 'number'],
                ],
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'array',
                'description' => 'Query results',
                'items' => ['type' => 'object'],
            ],
            'count' => [
                'type' => 'object',
                'description' => 'Query metadata',
                'properties' => [
                    'affected_rows' => ['type' => 'number'],
                    'last_insert_id' => ['type' => 'number'],
                    'execution_time' => ['type' => 'number'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $queryType = $properties['query_type'] ?? 'select';

        // Validate raw query
        if ($queryType === 'raw') {
            if (empty($properties['raw_query'])) {
                return false;
            }
        } else {
            // Validate table name
            if (empty($properties['table'])) {
                return false;
            }

            // Basic table name validation
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $properties['table'])) {
                return false;
            }
        }

        // Validate where conditions
        if (isset($properties['where'])) {
            $where = $properties['where'];
            if (isset($where['column']) && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $where['column'])) {
                return false;
            }
        }

        return true;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $startTime = microtime(true);

            // Merge input data with properties
            $queryConfig = array_merge($properties, array_filter($inputData));

            $context->log("Executing database query", [
                'query_type' => $queryConfig['query_type'] ?? 'select',
                'table' => $queryConfig['table'] ?? null,
            ]);

            // Execute query based on type
            $result = $this->executeQuery($queryConfig);
            $executionTime = microtime(true) - $startTime;

            $metadata = [
                'execution_time' => round($executionTime * 1000, 2),
                'affected_rows' => $result['affected_rows'] ?? count($result['data'] ?? []),
                'query_type' => $queryConfig['query_type'] ?? 'select',
            ];

            $context->log("Database query completed", [
                'execution_time' => $metadata['execution_time'] . 'ms',
                'affected_rows' => $metadata['affected_rows'],
            ]);

            return NodeExecutionResult::success($result['data'], [
                'count' => $metadata,
            ]);

        } catch (\Exception $e) {
            $context->log("Database query failed", [
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e);
        }
    }

    public function canHandle(array $inputData): bool
    {
        return true; // Database node can handle any input data
    }

    public function getMaxExecutionTime(): int
    {
        return 300; // Database queries can take up to 5 minutes
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => false,
            'databaseEnabled' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false; // For now, keep it synchronous
    }

    public function getPriority(): int
    {
        return 3; // Medium priority
    }

    public function getTags(): array
    {
        return ['database', 'query', 'sql', 'data', 'mysql', 'postgresql', 'sqlite'];
    }

    /**
     * Execute the database query
     */
    private function executeQuery(array $config): array
    {
        $queryType = $config['query_type'] ?? 'select';
        $connection = $config['connection'] ?? 'mysql';

        switch ($queryType) {
            case 'select':
                return $this->executeSelectQuery($config, $connection);

            case 'insert':
                return $this->executeInsertQuery($config, $connection);

            case 'update':
                return $this->executeUpdateQuery($config, $connection);

            case 'delete':
                return $this->executeDeleteQuery($config, $connection);

            case 'raw':
                return $this->executeRawQuery($config, $connection);

            default:
                throw new \InvalidArgumentException("Unsupported query type: {$queryType}");
        }
    }

    /**
     * Execute SELECT query
     */
    private function executeSelectQuery(array $config, string $connection): array
    {
        $table = $config['table'];
        $columns = $config['columns'] ?? '*';
        $limit = $config['limit'] ?? 100;

        $query = DB::connection($connection)->table($table);

        // Add columns
        if ($columns !== '*') {
            $columnArray = array_map('trim', explode(',', $columns));
            $query->select($columnArray);
        }

        // Add where conditions
        if (isset($config['where'])) {
            $where = $config['where'];
            if (isset($where['column'], $where['operator'], $where['value'])) {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }

        // Add order by
        if (isset($config['order_by'])) {
            $orderParts = explode(' ', $config['order_by'], 2);
            $query->orderBy($orderParts[0], $orderParts[1] ?? 'ASC');
        }

        // Add limit
        $query->limit($limit);

        $results = $query->get()->toArray();

        return [
            'data' => $results,
            'affected_rows' => count($results),
        ];
    }

    /**
     * Execute INSERT query
     */
    private function executeInsertQuery(array $config, string $connection): array
    {
        $table = $config['table'];
        $data = $config['parameters'] ?? [];

        if (empty($data)) {
            throw new \InvalidArgumentException("No data provided for INSERT query");
        }

        $insertId = DB::connection($connection)->table($table)->insertGetId($data);

        return [
            'data' => ['insert_id' => $insertId],
            'affected_rows' => 1,
            'last_insert_id' => $insertId,
        ];
    }

    /**
     * Execute UPDATE query
     */
    private function executeUpdateQuery(array $config, string $connection): array
    {
        $table = $config['table'];
        $data = $config['parameters'] ?? [];
        $where = $config['where'] ?? [];

        if (empty($data)) {
            throw new \InvalidArgumentException("No data provided for UPDATE query");
        }

        $query = DB::connection($connection)->table($table);

        // Add where conditions
        if (isset($where['column'], $where['operator'], $where['value'])) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }

        $affectedRows = $query->update($data);

        return [
            'data' => ['affected_rows' => $affectedRows],
            'affected_rows' => $affectedRows,
        ];
    }

    /**
     * Execute DELETE query
     */
    private function executeDeleteQuery(array $config, string $connection): array
    {
        $table = $config['table'];
        $where = $config['where'] ?? [];

        $query = DB::connection($connection)->table($table);

        // Add where conditions
        if (isset($where['column'], $where['operator'], $where['value'])) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }

        $affectedRows = $query->delete();

        return [
            'data' => ['affected_rows' => $affectedRows],
            'affected_rows' => $affectedRows,
        ];
    }

    /**
     * Execute RAW query
     */
    private function executeRawQuery(array $config, string $connection): array
    {
        $rawQuery = $config['raw_query'];
        $parameters = $config['parameters'] ?? [];

        if (empty($rawQuery)) {
            throw new \InvalidArgumentException("No raw query provided");
        }

        // For SELECT queries, use select method
        if (stripos(trim($rawQuery), 'SELECT') === 0) {
            $results = DB::connection($connection)->select($rawQuery, $parameters);
            return [
                'data' => array_map(function ($row) {
                    return (array) $row;
                }, $results),
                'affected_rows' => count($results),
            ];
        }

        // For other queries, use statement method
        $affectedRows = DB::connection($connection)->statement($rawQuery, $parameters);

        return [
            'data' => ['success' => $affectedRows],
            'affected_rows' => $affectedRows ? 1 : 0,
        ];
    }
}
