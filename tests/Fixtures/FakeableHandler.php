<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Fixtures;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Data\ColumnChange;

class FakeableHandler extends ColumnWatcher
{
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
