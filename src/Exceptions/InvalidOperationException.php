<?php

namespace Ascend\LaravelColumnWatcher\Exceptions;

use Exception;

class InvalidOperationException extends Exception
{
    public static function noOperations(string $handlerClass): static
    {
        return new static(
            "Handler [{$handlerClass}] is registered with both onCreate and onUpdate disabled, so it can never fire. ".
            'Enable at least one of them.'
        );
    }
}
