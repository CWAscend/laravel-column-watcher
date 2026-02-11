<?php

namespace Ascend\LaravelColumnWatcher\Exceptions;

use Exception;

class InvalidHandlerException extends Exception
{
    public static function doesNotExtendColumnWatcher(string $handlerClass): static
    {
        return new static(
            "Handler [{$handlerClass}] must extend ColumnWatcher."
        );
    }

    public static function classDoesNotExist(string $handlerClass): static
    {
        return new static(
            "Handler class [{$handlerClass}] does not exist."
        );
    }
}
