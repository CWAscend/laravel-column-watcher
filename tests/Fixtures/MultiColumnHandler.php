<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Fixtures;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Data\ColumnChange;

class MultiColumnHandler extends ColumnWatcher
{
    public static array $calls = [];

    protected function execute(ColumnChange $change): void
    {
        static::$calls[] = [
            'model_id' => $change->model->id,
            'column' => $change->column,
            'old_value' => $change->oldValue,
            'new_value' => $change->newValue,
        ];
    }

    public static function reset(): void
    {
        static::$calls = [];
        static::stopFaking();
    }
}
