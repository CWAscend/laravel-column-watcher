<?php

namespace CWAscend\LaravelColumnWatcher;

use CWAscend\LaravelColumnWatcher\Concerns\Fakeable;
use CWAscend\LaravelColumnWatcher\Data\ColumnChange;
use CWAscend\LaravelColumnWatcher\Enums\Timing;
use CWAscend\LaravelColumnWatcher\Events\WatcherFailed;
use CWAscend\LaravelColumnWatcher\Events\WatcherStarted;
use CWAscend\LaravelColumnWatcher\Events\WatcherSucceeded;
use CWAscend\LaravelColumnWatcher\Support\ColumnWatcherState;
use CWAscend\LaravelColumnWatcher\Support\WatcherRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Base class for all column watchers.
 *
 * Extend this class to create a watcher. Add `implements ShouldQueue`
 * if you want the watcher to run in the background via the queue.
 *
 * Note: When using ShouldQueue, the model is serialized using Laravel's
 * SerializesModels trait. If the model is deleted before the queue processes
 * the job, a ModelNotFoundException will be thrown. Consider this when
 * watching models that may be deleted shortly after updates.
 */
abstract class ColumnWatcher
{
    use Dispatchable, Fakeable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new watcher instance.
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $column,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
    ) {}

    /**
     * Handle the watcher execution.
     */
    public function handle(): void
    {
        $change = $this->toColumnChange();

        if (static::isFaking()) {
            static::recordChange($change);

            return;
        }

        WatcherStarted::dispatch($this);

        try {
            $this->execute($change);
            WatcherSucceeded::dispatch($this);
        } catch (Throwable $e) {
            WatcherFailed::dispatch($this, $e);
            throw $e;
        }
    }

    /**
     * Execute the watcher logic.
     */
    abstract protected function execute(ColumnChange $change): void;

    /**
     * Convert properties to a ColumnChange object.
     */
    protected function toColumnChange(): ColumnChange
    {
        return new ColumnChange(
            model: $this->model,
            column: $this->column,
            oldValue: $this->oldValue,
            newValue: $this->newValue,
        );
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName(): string
    {
        return static::class;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            static::class,
            get_class($this->model).':'.$this->model->getKey(),
            'column:'.$this->column,
        ];
    }

    /**
     * Register a watcher for a model column.
     */
    public static function register(
        string $modelClass,
        string|array $columns,
        string $handler,
        Timing $timing = Timing::SAVED
    ): void {
        app(WatcherRegistry::class)->register($modelClass, $columns, $handler, $timing);
    }

    /**
     * Get the scoped state instance.
     */
    protected static function state(): ColumnWatcherState
    {
        return app(ColumnWatcherState::class);
    }

    /**
     * Disable all watchers globally.
     */
    public static function disable(): void
    {
        static::state()->enabled = false;
    }

    /**
     * Enable all watchers globally.
     */
    public static function enable(): void
    {
        static::state()->enabled = true;
    }

    /**
     * Check if watchers are enabled globally.
     */
    public static function isEnabled(): bool
    {
        return static::state()->enabled && config('column-watcher.enabled', true);
    }

    /**
     * Check if a model is currently being processed (for loop detection).
     */
    public static function isProcessing(Model $model, string $column): bool
    {
        return static::state()->isProcessing($model, $column);
    }

    /**
     * Mark a model as being processed.
     */
    public static function startProcessing(Model $model, string $column): void
    {
        static::state()->startProcessing($model, $column);
    }

    /**
     * Mark a model as finished processing.
     */
    public static function finishProcessing(Model $model, string $column): void
    {
        static::state()->finishProcessing($model, $column);
    }
}
