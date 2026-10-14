<?php

namespace Ascend\LaravelColumnWatcher\Support;

use Ascend\LaravelColumnWatcher\Enums\Operation;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

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
     * Whether to bypass DB::afterCommit() for queued handlers.
     *
     * When true, queued handlers are dispatched immediately instead of
     * waiting for the transaction to commit. This is useful for testing
     * with DatabaseTransactions trait, where transactions never commit.
     */
    public bool $withoutAfterCommit = false;

    /**
     * The operation each in-flight save is performing, recorded at `saving`.
     *
     * By `saved` an inserted model already exists, and wasRecentlyCreated stays true for
     * every later save of the same instance, so neither can tell a create from an update.
     *
     * @var WeakMap<Model, Operation>
     */
    public WeakMap $operations;

    public function __construct()
    {
        $this->operations = new WeakMap;
    }

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
        $this->withoutAfterCommit = false;
        $this->operations = new WeakMap;
    }
}
