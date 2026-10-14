<?php

namespace Ascend\LaravelColumnWatcher\Exceptions;

use Ascend\LaravelColumnWatcher\Enums\Timing;
use Exception;

class InvalidTimingException extends Exception
{
    public static function queueableWithSaving(string $handlerClass): static
    {
        return new static(
            "Handler [{$handlerClass}] implements ShouldQueue but uses Timing::SAVING. ".
            "Queueable handlers must use Timing::SAVED because the save hasn't happened yet when SAVING fires."
        );
    }

    public static function queueableBeforeSave(string $handlerClass, Timing $timing): static
    {
        return new static(
            "Handler [{$handlerClass}] implements ShouldQueue but uses Timing::{$timing->name}. ".
            'Queueable handlers must use an after-save timing (SAVED, CREATED or UPDATED) because the save '.
            "hasn't happened yet when {$timing->name} fires."
        );
    }

    public static function overlapping(string $handlerClass, string $column, Timing $timing, Timing $other): static
    {
        return new static(
            "Handler [{$handlerClass}] is registered for [{$column}] with both Timing::{$other->name} and ".
            "Timing::{$timing->name}, which fire during the same save, so it would run twice for one change."
        );
    }
}
