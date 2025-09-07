<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'execution_id',
        'node_id',
        'level',
        'message',
        'context',
        'timestamp',
    ];

    protected $casts = [
        'context' => 'array',
        'timestamp' => 'datetime',
    ];

    /**
     * Get the execution this log belongs to
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }

    /**
     * Create a log entry
     */
    public static function log(
        Execution $execution,
        string $nodeId,
        string $level,
        string $message,
        array $context = []
    ): self {
        return static::create([
            'execution_id' => $execution->id,
            'node_id' => $nodeId,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => now(),
        ]);
    }

    /**
     * Log an info message
     */
    public static function info(Execution $execution, string $nodeId, string $message, array $context = []): self
    {
        return static::log($execution, $nodeId, 'info', $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning(Execution $execution, string $nodeId, string $message, array $context = []): self
    {
        return static::log($execution, $nodeId, 'warning', $message, $context);
    }

    /**
     * Log an error message
     */
    public static function error(Execution $execution, string $nodeId, string $message, array $context = []): self
    {
        return static::log($execution, $nodeId, 'error', $message, $context);
    }

    /**
     * Log a debug message
     */
    public static function debug(Execution $execution, string $nodeId, string $message, array $context = []): self
    {
        return static::log($execution, $nodeId, 'debug', $message, $context);
    }

    /**
     * Scope for execution logs
     */
    public function scopeForExecution($query, Execution $execution)
    {
        return $query->where('execution_id', $execution->id);
    }

    /**
     * Scope for node logs
     */
    public function scopeForNode($query, string $nodeId)
    {
        return $query->where('node_id', $nodeId);
    }

    /**
     * Scope for log level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for error logs
     */
    public function scopeErrors($query)
    {
        return $query->where('level', 'error');
    }

    /**
     * Scope for warning logs
     */
    public function scopeWarnings($query)
    {
        return $query->where('level', 'warning');
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Get log level display
     */
    public function getLevelDisplayAttribute(): string
    {
        return match ($this->level) {
            'info' => 'Info',
            'warning' => 'Warning',
            'error' => 'Error',
            'debug' => 'Debug',
            default => 'Unknown'
        };
    }

    /**
     * Get formatted timestamp
     */
    public function getFormattedTimestampAttribute(): string
    {
        return $this->timestamp->format('Y-m-d H:i:s.u');
    }
}
