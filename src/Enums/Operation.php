<?php

namespace Ascend\LaravelColumnWatcher\Enums;

/**
 * Whether a save inserted the model or updated an existing row.
 */
enum Operation
{
    case CREATE;
    case UPDATE;
}
