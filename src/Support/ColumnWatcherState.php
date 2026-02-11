<?php

namespace CWAscend\LaravelColumnWatcher\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Holds mutable runtime state for column watchers.
 *
 * This class is registered as a scoped singleton to ensure state
 * is automatically reset between requests, which is essential for
 * Laravel Octane compatibility.
 */
class ColumnWatcherState
{
    /**
     * Whether watchers are enabled globally.
     */
    public bool $enabled = true;

    /**
     * Models currently being processed (for infinite loop protection).
     *
     * @var array<string, bool>
     */
    public array $processing = [];

    /**
     * Generate a unique key for a model/column combination.
     */
    public function key(Model $model, string $column): string
    {
        return get_class($model).':'.$model->getKey().':'.$column;
    }

    /**
     * Check if a model column is currently being processed.
     */
    public function isProcessing(Model $model, string $column): bool
    {
        return isset($this->processing[$this->key($model, $column)]);
    }

    /**
     * Mark a model column as being processed.
     */
    public function startProcessing(Model $model, string $column): void
    {
        $this->processing[$this->key($model, $column)] = true;
    }

    /**
     * Mark a model column as finished processing.
     */
    public function finishProcessing(Model $model, string $column): void
    {
        unset($this->processing[$this->key($model, $column)]);
    }

    /**
     * Reset all state to defaults.
     *
     * Useful for testing or manual reset scenarios.
     */
    public function reset(): void
    {
        $this->enabled = true;
        $this->processing = [];
    }
}
