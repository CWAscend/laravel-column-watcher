<?php

namespace CWAscend\LaravelColumnWatcher\Exceptions;

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
}
