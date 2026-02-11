<?php

namespace Ascend\LaravelColumnWatcher\Concerns;

use Ascend\LaravelColumnWatcher\Data\ColumnChange;
use Closure;
use PHPUnit\Framework\Assert;

trait Fakeable
{
    /**
     * Recorded changes when faking, keyed by class name.
     *
     * @var array<string, array<ColumnChange>>
     */
    protected static array $recorded = [];

    /**
     * Classes that are currently faking, keyed by class name.
     *
     * @var array<string, bool>
     */
    protected static array $faking = [];

    /**
     * Start faking this watcher.
     */
    public static function fake(): void
    {
        static::$faking[static::class] = true;
        static::$recorded[static::class] = [];
    }

    /**
     * Stop faking and clear recorded changes.
     */
    public static function stopFaking(): void
    {
        unset(static::$faking[static::class]);
        unset(static::$recorded[static::class]);
    }

    /**
     * Check if currently faking.
     */
    public static function isFaking(): bool
    {
        return static::$faking[static::class] ?? false;
    }

    /**
     * Get all recorded column changes.
     *
     * @return array<ColumnChange>
     */
    public static function recorded(): array
    {
        return static::$recorded[static::class] ?? [];
    }

    /**
     * Record a change (called internally during faking).
     */
    protected static function recordChange(ColumnChange $change): void
    {
        if (! isset(static::$recorded[static::class])) {
            static::$recorded[static::class] = [];
        }

        static::$recorded[static::class][] = $change;
    }

    /**
     * Assert the watcher was triggered at least once.
     */
    public static function assertTriggered(?Closure $callback = null, ?string $message = null): void
    {
        $recorded = static::recorded();

        Assert::assertNotEmpty(
            $recorded,
            $message ?? 'Expected ['.static::class.'] to be triggered but it was not.'
        );

        if ($callback) {
            $matched = collect($recorded)->first($callback);

            Assert::assertNotNull(
                $matched,
                $message ?? 'The watcher was triggered but no call matched the given conditions.'
            );
        }
    }

    /**
     * Assert the watcher was not triggered.
     */
    public static function assertNotTriggered(?string $message = null): void
    {
        $recorded = static::recorded();

        Assert::assertEmpty(
            $recorded,
            $message ?? 'Expected ['.static::class.'] not to be triggered but it was triggered '.count($recorded).' time(s).'
        );
    }

    /**
     * Assert the watcher was triggered exactly N times.
     */
    public static function assertTriggeredTimes(int $times, ?string $message = null): void
    {
        $recorded = static::recorded();

        Assert::assertCount(
            $times,
            $recorded,
            $message ?? 'Expected ['.static::class.'] to be triggered '.$times.' time(s) but was triggered '.count($recorded).' time(s).'
        );
    }

    /**
     * Assert the watcher was triggered for a specific column.
     */
    public static function assertTriggeredForColumn(string $column, ?string $message = null): void
    {
        $matched = collect(static::recorded())->first(fn (ColumnChange $change) => $change->column === $column);

        Assert::assertNotNull(
            $matched,
            $message ?? 'Expected ['.static::class."] to be triggered for column [{$column}] but it was not."
        );
    }

    /**
     * Assert the watcher was triggered with specific old and new values.
     */
    public static function assertTriggeredWithValues(mixed $oldValue, mixed $newValue, ?string $message = null): void
    {
        $matched = collect(static::recorded())->first(
            fn (ColumnChange $change) => $change->oldValue === $oldValue && $change->newValue === $newValue
        );

        Assert::assertNotNull(
            $matched,
            $message ?? 'Expected ['.static::class."] to be triggered with values [{$oldValue}] -> [{$newValue}] but it was not."
        );
    }
}
