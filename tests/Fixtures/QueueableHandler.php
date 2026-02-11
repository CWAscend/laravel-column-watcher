<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Fixtures;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Data\ColumnChange;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueueableHandler extends ColumnWatcher implements ShouldQueue
{
    public $connection = 'redis';

    public $queue = 'watchers';

    public $tries = 5;

    public static array $executed = [];

    protected function execute(ColumnChange $change): void
    {
        static::$executed[] = [
            'model_id' => $change->model->id,
            'column' => $change->column,
            'old_value' => $change->oldValue,
            'new_value' => $change->newValue,
        ];
    }

    public static function reset(): void
    {
        static::$executed = [];
        static::stopFaking();
    }
}
