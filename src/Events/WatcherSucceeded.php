<?php

namespace Ascend\LaravelColumnWatcher\Events;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Illuminate\Foundation\Events\Dispatchable;

class WatcherSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly ColumnWatcher $watcher,
    ) {}
}
