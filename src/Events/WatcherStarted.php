<?php

namespace CWAscend\LaravelColumnWatcher\Events;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use Illuminate\Foundation\Events\Dispatchable;

class WatcherStarted
{
    use Dispatchable;

    public function __construct(
        public readonly ColumnWatcher $watcher,
    ) {}
}
