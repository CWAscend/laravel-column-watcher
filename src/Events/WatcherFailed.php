<?php

namespace Ascend\LaravelColumnWatcher\Events;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class WatcherFailed
{
    use Dispatchable;

    public function __construct(
        public readonly ColumnWatcher $watcher,
        public readonly Throwable $exception,
    ) {}
}
