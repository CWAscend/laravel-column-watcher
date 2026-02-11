<?php

namespace Ascend\LaravelColumnWatcher\Tests\Fixtures;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Data\ColumnChange;

class LoopingHandler extends ColumnWatcher
{
    public static int $callCount = 0;

    protected function execute(ColumnChange $change): void
    {
        static::$callCount++;

        // This would cause an infinite loop without protection
        $change->model->status = $change->newValue.'_modified';
        $change->model->save();
    }

    public static function reset(): void
    {
        static::$callCount = 0;
        static::stopFaking();
    }
}
