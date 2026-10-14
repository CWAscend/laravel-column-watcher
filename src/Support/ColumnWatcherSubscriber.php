<?php

namespace Ascend\LaravelColumnWatcher\Support;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
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

            $creating = ! $model->exists;
            $this->state()->creating[$model] = $creating;

            $this->handleModelEvent($model, Timing::SAVING);
            $this->handleModelEvent($model, $creating ? Timing::CREATING : Timing::UPDATING);
        });

        $events->listen('eloquent.saved: *', function (string $event, array $payload) {
            [$model] = $payload;

            $state = $this->state();
            $creating = $state->creating[$model] ?? false;
            unset($state->creating[$model]);

            if ($creating) {
                $this->handleModelEvent($model, Timing::CREATED);

                return;
            }

            // SAVED fires after updates only — see Timing::SAVED.
            $this->handleModelEvent($model, Timing::UPDATED);
            $this->handleModelEvent($model, Timing::SAVED);
        });
    }

    /**
     * Handle a model event.
     */
    protected function handleModelEvent(Model $model, Timing $timing): void
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

            // isDirty() is right for every timing: Laravel only syncs the original
            // attributes after the `saved` listeners have run. wasChanged() is not —
            // an insert never records changes, and a save with nothing dirty leaves
            // the previous save's changes in place.
            if (! $model->isDirty($column)) {
                continue;
            }

            $this->dispatchHandlers($model, $timing, $column);
        }
    }

    /**
     * Dispatch all handlers for a column change.
     */
    protected function dispatchHandlers(Model $model, Timing $timing, string $column): void
    {
        $modelClass = get_class($model);
        $handlers = $this->registry->getHandlers($modelClass, $timing, $column);

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
