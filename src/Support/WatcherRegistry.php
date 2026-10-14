<?php

namespace Ascend\LaravelColumnWatcher\Support;

use Ascend\LaravelColumnWatcher\Attributes\Watch;
use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Enums\Operation;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Ascend\LaravelColumnWatcher\Exceptions\InvalidHandlerException;
use Ascend\LaravelColumnWatcher\Exceptions\InvalidOperationException;
use Ascend\LaravelColumnWatcher\Exceptions\InvalidTimingException;
use Illuminate\Contracts\Queue\ShouldQueue;
use ReflectionClass;

class WatcherRegistry
{
    /**
     * Registered watchers.
     *
     * Structure: [ModelClass][timing][column][] = registration
     *
     * @var array<string, array<string, array<string, array<int, array{handler: string, onCreate: bool, onUpdate: bool}>>>>
     */
    protected array $watchers = [];

    /**
     * Cache of scanned model classes.
     *
     * @var array<string, bool>
     */
    protected array $scannedModels = [];

    /**
     * Register a watcher for a model column.
     *
     * @throws InvalidHandlerException
     * @throws InvalidTimingException
     * @throws InvalidOperationException
     */
    public function register(
        string $modelClass,
        string|array $columns,
        string $handlerClass,
        Timing $timing = Timing::SAVED,
        ?bool $onCreate = null,
        ?bool $onUpdate = null,
    ): void {
        $this->validateHandler($handlerClass);
        $this->validateNotQueueableWithSaving($handlerClass, $timing);

        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            $this->store($modelClass, $timing, $column, $handlerClass, $onCreate, $onUpdate);
        }
    }

    /**
     * Get all handlers for a specific model, timing, and column.
     *
     * When an operation is given, only handlers registered for it are returned.
     *
     * @return array<string>
     */
    public function getHandlers(string $modelClass, Timing $timing, string $column, ?Operation $operation = null): array
    {
        $this->ensureModelScanned($modelClass);

        $registrations = $this->watchers[$modelClass][$this->timingKey($timing)][$column] ?? [];

        return array_values(array_map(
            fn (array $registration): string => $registration['handler'],
            array_filter($registrations, fn (array $registration): bool => match ($operation) {
                null => true,
                Operation::CREATE => $registration['onCreate'],
                Operation::UPDATE => $registration['onUpdate'],
            }),
        ));
    }

    /**
     * Get all watched columns for a model and timing.
     *
     * @return array<string>
     */
    public function getWatchedColumns(string $modelClass, Timing $timing): array
    {
        $this->ensureModelScanned($modelClass);

        return array_keys($this->watchers[$modelClass][$this->timingKey($timing)] ?? []);
    }

    /**
     * Check if a model has any watchers registered.
     */
    public function hasWatchers(string $modelClass): bool
    {
        $this->ensureModelScanned($modelClass);

        return isset($this->watchers[$modelClass]) && ! empty($this->watchers[$modelClass]);
    }

    /**
     * Scan model class for Watch attributes and register them.
     *
     * @throws InvalidHandlerException
     * @throws InvalidTimingException
     */
    public function scanModel(string $modelClass): void
    {
        if (isset($this->scannedModels[$modelClass])) {
            return;
        }

        $this->scannedModels[$modelClass] = true;

        if (! class_exists($modelClass)) {
            return;
        }

        $reflection = new ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes(Watch::class);

        foreach ($attributes as $attribute) {
            $watch = $attribute->newInstance();

            $this->validateHandler($watch->handler);
            $this->validateNotQueueableWithSaving($watch->handler, $watch->timing);

            foreach ($watch->columns as $column) {
                $this->store($modelClass, $watch->timing, $column, $watch->handler, $watch->onCreate, $watch->onUpdate);
            }
        }
    }

    /**
     * Record a single handler registration.
     *
     * Unset operation flags resolve to the behaviour each timing had before they existed, so
     * upgrading never changes when an existing watcher fires: SAVED fires on update only
     * (creates are opt-in) and SAVING fires on both.
     *
     * @throws InvalidOperationException
     */
    protected function store(
        string $modelClass,
        Timing $timing,
        string $column,
        string $handlerClass,
        ?bool $onCreate,
        ?bool $onUpdate,
    ): void {
        $onCreate ??= $timing === Timing::SAVING;
        $onUpdate ??= true;

        $this->validateHasOperation($handlerClass, $onCreate, $onUpdate);

        $this->watchers[$modelClass][$this->timingKey($timing)][$column][] = [
            'handler' => $handlerClass,
            'onCreate' => $onCreate,
            'onUpdate' => $onUpdate,
        ];
    }

    /**
     * Ensure a model has been scanned for attributes.
     */
    protected function ensureModelScanned(string $modelClass): void
    {
        if (! isset($this->scannedModels[$modelClass])) {
            $this->scanModel($modelClass);
        }
    }

    /**
     * Convert Timing enum to string key for internal storage.
     */
    protected function timingKey(Timing $timing): string
    {
        return $timing->name;
    }

    /**
     * Validate that the handler class extends ColumnWatcher.
     *
     * @throws InvalidHandlerException
     */
    protected function validateHandler(string $handlerClass): void
    {
        if (! class_exists($handlerClass)) {
            throw InvalidHandlerException::classDoesNotExist($handlerClass);
        }

        if (! is_subclass_of($handlerClass, ColumnWatcher::class)) {
            throw InvalidHandlerException::doesNotExtendColumnWatcher($handlerClass);
        }
    }

    /**
     * Validate that queueable handlers don't use SAVING timing.
     *
     * @throws InvalidTimingException
     */
    protected function validateNotQueueableWithSaving(string $handlerClass, Timing $timing): void
    {
        if ($timing === Timing::SAVING && is_subclass_of($handlerClass, ShouldQueue::class)) {
            throw InvalidTimingException::queueableWithSaving($handlerClass);
        }
    }

    /**
     * Validate that a handler fires on at least one operation.
     *
     * @throws InvalidOperationException
     */
    protected function validateHasOperation(string $handlerClass, bool $onCreate, bool $onUpdate): void
    {
        if (! $onCreate && ! $onUpdate) {
            throw InvalidOperationException::noOperations($handlerClass);
        }
    }

    /**
     * Clear all registered watchers (useful for testing).
     */
    public function clear(): void
    {
        $this->watchers = [];
        $this->scannedModels = [];
    }

    /**
     * Get all registered watchers.
     *
     * Returns a flat array of watcher registrations for listing.
     *
     * @return array<int, array{model: string, column: string, timing: Timing, handler: string, onCreate: bool, onUpdate: bool}>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->watchers as $modelClass => $timings) {
            foreach ($timings as $timingKey => $columns) {
                $timing = $this->timingFromKey($timingKey);

                foreach ($columns as $column => $registrations) {
                    foreach ($registrations as $registration) {
                        $result[] = [
                            'model' => $modelClass,
                            'column' => $column,
                            'timing' => $timing,
                            ...$registration,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Convert string key back to Timing enum.
     */
    protected function timingFromKey(string $key): Timing
    {
        return match ($key) {
            'SAVING' => Timing::SAVING,
            'SAVED' => Timing::SAVED,
        };
    }

    /**
     * Scan multiple model classes for Watch attributes.
     *
     * @param  array<string>  $modelClasses
     */
    public function scanModels(array $modelClasses): void
    {
        foreach ($modelClasses as $modelClass) {
            $this->scanModel($modelClass);
        }
    }
}
