<?php

namespace Ascend\LaravelColumnWatcher\Enums;

/**
 * When a watcher runs, named after the Eloquent model event it corresponds to.
 *
 * SAVED is the one exception to that naming: it fires after updates only, not after creates,
 * because that is how it has always behaved. Use CREATED and/or UPDATED to be explicit.
 */
enum Timing
{
    /** Before the row is written, on create and update. */
    case SAVING;

    /** After an existing row is updated. Does not fire on create. */
    case SAVED;

    /** Before a new row is inserted. */
    case CREATING;

    /** After a new row is inserted. */
    case CREATED;

    /** Before an existing row is updated. */
    case UPDATING;

    /** After an existing row is updated. */
    case UPDATED;

    /**
     * Whether this timing runs before the row is written — too early for a queued handler,
     * which would be serialised before the change exists.
     */
    public function isBeforeSave(): bool
    {
        return match ($this) {
            self::SAVING, self::CREATING, self::UPDATING => true,
            self::SAVED, self::CREATED, self::UPDATED => false,
        };
    }

    /**
     * Whether this timing and another fire during the same save, so one handler registered
     * for both would run twice for a single change.
     */
    public function overlaps(Timing $other): bool
    {
        return in_array($other, match ($this) {
            self::SAVING => [self::CREATING, self::UPDATING],
            self::CREATING, self::UPDATING => [self::SAVING],
            self::SAVED => [self::UPDATED],
            self::UPDATED => [self::SAVED],
            self::CREATED => [],
        }, true);
    }
}
