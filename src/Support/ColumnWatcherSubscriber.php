<?php

namespace Ascend\LaravelColumnWatcher\Support;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Enums\Operation;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class ColumnWatcherSubscriber
{
    public function __construct(
        protected WatcherRegistry $registry,
    ) {}

    /**
     * Subscribe to Eloquent model events.
     *
     * Wildcard listeners receive the event name and an array of arguments.
     * For Eloquent model events, the first argument is always the model instance.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.saving: *', function (string $event, array $payload) {
            [$model] = $payload;

            $operation = $model->exists ? Operation::UPDATE : Operation::CREATE;
            $this->state()->operations[$model] = $operation;

            $this->handleModelEvent($model, Timing::SAVING, $operation);
        });

        $events->listen('eloquent.saved: *', function (string $event, array $payload) {
            [$model] = $payload;

            $operations = $this->state()->operations;
            $operation = $operations[$model] ?? Operation::UPDATE;
            unset($operations[$model]);

            $this->handleModelEvent($model, Timing::SAVED, $operation);
        });
    }

    /**
     * Handle a model event.
     */
    protected function handleModelEvent(Model $model, Timing $timing, Operation $operation): void
    {
        if (! ColumnWatcher::isEnabled()) {
            return;
        }

        $modelClass = get_class($model);
        $watchedColumns = $this->registry->getWatchedColumns($modelClass, $timing);

        foreach ($watchedColumns as $column) {
            // Skip if this model+column is already being processed (prevents infinite loops)
            if (ColumnWatcher::isProcessing($model, $column)) {
                continue;
            }

            // isDirty() is right for both timings: Laravel only syncs the original
            // attributes after the `saved` listeners have run. wasChanged() is not —
            // an insert never records changes, and a save with nothing dirty leaves
            // the previous save's changes in place.
            if (! $model->isDirty($column)) {
                continue;
            }

            $this->dispatchHandlers($model, $timing, $column, $operation);
        }
    }

    /**
     * Dispatch all handlers for a column change.
     */
    protected function dispatchHandlers(Model $model, Timing $timing, string $column, Operation $operation): void
    {
        $modelClass = get_class($model);
        $handlers = $this->registry->getHandlers($modelClass, $timing, $column, $operation);

        foreach ($handlers as $handlerClass) {
            $watcher = new $handlerClass(
                model: $model,
                column: $column,
                oldValue: $model->getOriginal($column),
                newValue: $model->getAttribute($column),
            );

            if ($this->shouldQueue($handlerClass)) {
                // Dispatch queued handlers after the database transaction commits
                // This prevents jobs from processing changes that were rolled back
                $this->dispatchAfterCommit($watcher);
            } else {
                // For synchronous handlers, track processing to prevent infinite loops
                ColumnWatcher::startProcessing($model, $column);

                try {
                    $watcher->handle();
                } finally {
                    ColumnWatcher::finishProcessing($model, $column);
                }
            }
        }
    }

    /**
     * Dispatch a watcher to the queue after the current transaction commits.
     *
     * If not in a transaction, or if withoutAfterCommit is enabled (for testing),
     * dispatches immediately.
     */
    protected function dispatchAfterCommit(ColumnWatcher $watcher): void
    {
        $state = $this->state();

        if ($state->withoutAfterCommit || DB::transactionLevel() === 0) {
            dispatch($watcher);

            return;
        }

        DB::afterCommit(fn () => dispatch($watcher));
    }

    /**
     * Check if the handler should be queued.
     */
    protected function shouldQueue(string $handlerClass): bool
    {
        return is_subclass_of($handlerClass, ShouldQueue::class);
    }

    protected function state(): ColumnWatcherState
    {
        return app(ColumnWatcherState::class);
    }
}
