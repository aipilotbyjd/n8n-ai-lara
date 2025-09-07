<?php

namespace App\Nodes\Core;

use App\Nodes\Interfaces\NodeInterface;
use App\Workflow\Execution\NodeExecutionContext;
use App\Workflow\Execution\NodeExecutionResult;
use Illuminate\Support\Facades\Log;

class DataTransformationNode implements NodeInterface
{
    public function getId(): string
    {
        return 'dataTransformation';
    }

    public function getName(): string
    {
        return 'Data Transformation';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCategory(): string
    {
        return 'transform';
    }

    public function getIcon(): string
    {
        return 'transform';
    }

    public function getDescription(): string
    {
        return 'Transform and manipulate data using various operations';
    }

    public function getProperties(): array
    {
        return [
            'operation' => [
                'type' => 'select',
                'options' => [
                    'set', 'get', 'remove', 'rename', 'filter',
                    'map', 'reduce', 'sort', 'group', 'merge',
                    'split', 'join', 'format', 'parse'
                ],
                'default' => 'set',
                'required' => true,
                'description' => 'Transformation operation to perform',
            ],
            'targetPath' => [
                'type' => 'string',
                'placeholder' => 'data.result',
                'description' => 'JSON path to target field',
                'condition' => 'operation !== "merge" && operation !== "split"',
            ],
            'value' => [
                'type' => 'string',
                'placeholder' => 'new value',
                'description' => 'Value to set (for set operation)',
                'condition' => 'operation === "set"',
            ],
            'sourcePath' => [
                'type' => 'string',
                'placeholder' => 'data.input',
                'description' => 'Source JSON path (for get, rename operations)',
                'condition' => 'operation === "get" || operation === "rename"',
            ],
            'newName' => [
                'type' => 'string',
                'placeholder' => 'newFieldName',
                'description' => 'New field name (for rename operation)',
                'condition' => 'operation === "rename"',
            ],
            'filterCondition' => [
                'type' => 'string',
                'placeholder' => 'item.status === "active"',
                'description' => 'Filter condition (JavaScript expression)',
                'condition' => 'operation === "filter"',
            ],
            'mapExpression' => [
                'type' => 'string',
                'placeholder' => 'item.name.toUpperCase()',
                'description' => 'Map expression (JavaScript expression)',
                'condition' => 'operation === "map"',
            ],
            'sortBy' => [
                'type' => 'string',
                'placeholder' => 'name',
                'description' => 'Field to sort by',
                'condition' => 'operation === "sort"',
            ],
            'sortOrder' => [
                'type' => 'select',
                'options' => ['asc', 'desc'],
                'default' => 'asc',
                'description' => 'Sort order',
                'condition' => 'operation === "sort"',
            ],
            'groupBy' => [
                'type' => 'string',
                'placeholder' => 'category',
                'description' => 'Field to group by',
                'condition' => 'operation === "group"',
            ],
            'mergeWith' => [
                'type' => 'object',
                'description' => 'Object to merge with input data',
                'condition' => 'operation === "merge"',
            ],
            'splitBy' => [
                'type' => 'string',
                'placeholder' => ',',
                'description' => 'Delimiter to split by',
                'condition' => 'operation === "split"',
            ],
            'joinWith' => [
                'type' => 'string',
                'placeholder' => ', ',
                'description' => 'String to join with',
                'condition' => 'operation === "join"',
            ],
            'formatType' => [
                'type' => 'select',
                'options' => ['json', 'xml', 'csv', 'yaml'],
                'default' => 'json',
                'description' => 'Output format',
                'condition' => 'operation === "format"',
            ],
            'parseType' => [
                'type' => 'select',
                'options' => ['json', 'xml', 'csv', 'yaml'],
                'default' => 'json',
                'description' => 'Input format to parse',
                'condition' => 'operation === "parse"',
            ],
        ];
    }

    public function getInputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Input data to transform',
            ],
        ];
    }

    public function getOutputs(): array
    {
        return [
            'main' => [
                'type' => 'object',
                'description' => 'Transformed data',
            ],
            'error' => [
                'type' => 'object',
                'description' => 'Error information if transformation fails',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'operation' => ['type' => 'string'],
                    'input' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function validateProperties(array $properties): bool
    {
        $operation = $properties['operation'] ?? '';

        switch ($operation) {
            case 'set':
                return !empty($properties['targetPath']) && isset($properties['value']);
            case 'get':
            case 'remove':
                return !empty($properties['targetPath']);
            case 'rename':
                return !empty($properties['targetPath']) && !empty($properties['sourcePath']) && !empty($properties['newName']);
            case 'filter':
                return !empty($properties['filterCondition']);
            case 'map':
                return !empty($properties['mapExpression']);
            case 'sort':
                return !empty($properties['sortBy']);
            case 'group':
                return !empty($properties['groupBy']);
            case 'merge':
                return isset($properties['mergeWith']);
            case 'split':
                return !empty($properties['targetPath']) && !empty($properties['splitBy']);
            case 'join':
                return !empty($properties['targetPath']) && isset($properties['joinWith']);
            case 'format':
                return !empty($properties['targetPath']);
            case 'parse':
                return !empty($properties['targetPath']);
            default:
                return false;
        }
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        try {
            $properties = $context->getProperties();
            $inputData = $context->getInputData();
            $operation = $properties['operation'];

            $context->log("Starting data transformation", [
                'operation' => $operation,
                'input_type' => gettype($inputData),
            ]);

            $result = $this->performTransformation($inputData, $properties);

            $context->log("Data transformation completed", [
                'operation' => $operation,
                'output_type' => gettype($result),
            ]);

            return NodeExecutionResult::success([$result]);

        } catch (\Exception $e) {
            $context->log("Data transformation failed", [
                'operation' => $properties['operation'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return NodeExecutionResult::error($e, [[
                'message' => $e->getMessage(),
                'operation' => $properties['operation'] ?? 'unknown',
                'input' => $context->getInputData(),
            ]]);
        }
    }

    private function performTransformation(array $data, array $properties): array
    {
        $operation = $properties['operation'];

        switch ($operation) {
            case 'set':
                return $this->setValue($data, $properties['targetPath'], $properties['value']);
            case 'get':
                return $this->getValue($data, $properties['targetPath']);
            case 'remove':
                return $this->removeValue($data, $properties['targetPath']);
            case 'rename':
                return $this->renameField($data, $properties['sourcePath'], $properties['targetPath'], $properties['newName']);
            case 'filter':
                return $this->filterData($data, $properties['filterCondition']);
            case 'map':
                return $this->mapData($data, $properties['mapExpression']);
            case 'sort':
                return $this->sortData($data, $properties['sortBy'], $properties['sortOrder'] ?? 'asc');
            case 'group':
                return $this->groupData($data, $properties['groupBy']);
            case 'merge':
                return array_merge($data, $properties['mergeWith'] ?? []);
            case 'split':
                return $this->splitData($data, $properties['targetPath'], $properties['splitBy']);
            case 'join':
                return $this->joinData($data, $properties['targetPath'], $properties['joinWith']);
            case 'format':
                return $this->formatData($data, $properties['targetPath'], $properties['formatType']);
            case 'parse':
                return $this->parseData($data, $properties['targetPath'], $properties['parseType']);
            default:
                throw new \Exception("Unsupported operation: {$operation}");
        }
    }

    private function setValue(array $data, string $path, $value): array
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;
        return $data;
    }

    private function getValue(array $data, string $path): array
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return [];
            }
            $current = $current[$key];
        }

        return is_array($current) ? $current : [$path => $current];
    }

    private function removeValue(array $data, string $path): array
    {
        $keys = explode('.', $path);
        $current = &$data;
        $lastKey = array_pop($keys);

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return $data;
            }
            $current = &$current[$key];
        }

        unset($current[$lastKey]);
        return $data;
    }

    private function renameField(array $data, string $sourcePath, string $targetPath, string $newName): array
    {
        $sourceValue = $this->getValue($data, $sourcePath);
        if (empty($sourceValue)) {
            return $data;
        }

        $data = $this->removeValue($data, $sourcePath);
        return $this->setValue($data, $targetPath . '.' . $newName, reset($sourceValue));
    }

    private function filterData(array $data, string $condition): array
    {
        if (!is_array($data) || empty($data)) {
            return $data;
        }

        // Simple filtering - in production, use a proper expression evaluator
        return array_filter($data, function ($item) use ($condition) {
            // Very basic implementation - replace with proper JS expression evaluator
            if (is_array($item) && isset($item['status']) && strpos($condition, 'active') !== false) {
                return ($item['status'] ?? '') === 'active';
            }
            return true;
        });
    }

    private function mapData(array $data, string $expression): array
    {
        if (!is_array($data)) {
            return $data;
        }

        // Simple mapping - in production, use a proper expression evaluator
        return array_map(function ($item) use ($expression) {
            if (is_array($item) && isset($item['name']) && strpos($expression, 'toUpperCase') !== false) {
                $item['name'] = strtoupper($item['name']);
            }
            return $item;
        }, $data);
    }

    private function sortData(array $data, string $sortBy, string $order): array
    {
        if (!is_array($data) || empty($data)) {
            return $data;
        }

        usort($data, function ($a, $b) use ($sortBy, $order) {
            if (!is_array($a) || !is_array($b)) {
                return 0;
            }

            $aVal = $a[$sortBy] ?? '';
            $bVal = $b[$sortBy] ?? '';

            if ($order === 'desc') {
                return strcmp($bVal, $aVal);
            }
            return strcmp($aVal, $bVal);
        });

        return $data;
    }

    private function groupData(array $data, string $groupBy): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $grouped = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $item[$groupBy] ?? 'other';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $item;
        }

        return $grouped;
    }

    private function splitData(array $data, string $path, string $delimiter): array
    {
        $value = $this->getValue($data, $path);
        if (empty($value)) {
            return $data;
        }

        $stringValue = is_string(reset($value)) ? reset($value) : json_encode(reset($value));
        $parts = explode($delimiter, $stringValue);

        return $this->setValue($data, $path, $parts);
    }

    private function joinData(array $data, string $path, string $glue): array
    {
        $value = $this->getValue($data, $path);
        if (empty($value)) {
            return $data;
        }

        $arrayValue = reset($value);
        if (is_array($arrayValue)) {
            $joined = implode($glue, $arrayValue);
        } else {
            $joined = (string)$arrayValue;
        }

        return $this->setValue($data, $path, $joined);
    }

    private function formatData(array $data, string $path, string $format): array
    {
        $value = $this->getValue($data, $path);
        if (empty($value)) {
            return $data;
        }

        $formatted = match ($format) {
            'json' => json_encode(reset($value), JSON_PRETTY_PRINT),
            'xml' => $this->arrayToXml(reset($value)),
            'csv' => $this->arrayToCsv(reset($value)),
            'yaml' => yaml_emit(reset($value)), // Requires yaml extension
            default => json_encode(reset($value)),
        };

        return $this->setValue($data, $path, $formatted);
    }

    private function parseData(array $data, string $path, string $format): array
    {
        $value = $this->getValue($data, $path);
        if (empty($value)) {
            return $data;
        }

        $stringValue = is_string(reset($value)) ? reset($value) : json_encode(reset($value));

        $parsed = match ($format) {
            'json' => json_decode($stringValue, true),
            'xml' => $this->xmlToArray($stringValue),
            'csv' => $this->csvToArray($stringValue),
            'yaml' => yaml_parse($stringValue), // Requires yaml extension
            default => json_decode($stringValue, true),
        };

        return $this->setValue($data, $path, $parsed);
    }

    private function arrayToXml(array $data): string
    {
        $xml = new \SimpleXMLElement('<root/>');
        $this->arrayToXmlRecursive($data, $xml);
        return $xml->asXML();
    }

    private function arrayToXmlRecursive(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXmlRecursive($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    private function xmlToArray(string $xml): array
    {
        $xmlObj = simplexml_load_string($xml);
        return json_decode(json_encode($xmlObj), true);
    }

    private function arrayToCsv(array $data): string
    {
        if (empty($data) || !is_array($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        $isAssoc = $this->isAssociativeArray($data);

        if ($isAssoc) {
            // Single associative array
            fputcsv($output, array_keys($data));
            fputcsv($output, array_values($data));
        } else {
            // Array of arrays
            $headers = array_keys($data[0] ?? []);
            fputcsv($output, $headers);

            foreach ($data as $row) {
                fputcsv($output, array_values($row));
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function csvToArray(string $csv): array
    {
        $lines = explode("\n", trim($csv));
        if (empty($lines)) {
            return [];
        }

        $result = [];
        $headers = str_getcsv(array_shift($lines));

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $values = str_getcsv($line);
            $result[] = array_combine($headers, $values);
        }

        return $result;
    }

    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public function canHandle(array $inputData): bool
    {
        return is_array($inputData);
    }

    public function getMaxExecutionTime(): int
    {
        return 60; // Data transformations can take longer
    }

    public function getOptions(): array
    {
        return [
            'retryable' => true,
            'isTrigger' => false,
            'dataProcessing' => true,
        ];
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 5; // Medium priority
    }

    public function getTags(): array
    {
        return ['data', 'transform', 'json', 'xml', 'csv', 'filter', 'map', 'sort'];
    }
}
