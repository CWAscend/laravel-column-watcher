<?php

namespace Ascend\LaravelColumnWatcher\Tests\Unit;

use Ascend\LaravelColumnWatcher\Attributes\Watch;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\TestCase;

class WatchAttributeTest extends TestCase
{
    public function test_attribute_stores_single_column(): void
    {
        $watch = new Watch('status', StatusChangedHandler::class);

        $this->assertEquals(['status'], $watch->columns);
        $this->assertEquals(StatusChangedHandler::class, $watch->handler);
        $this->assertEquals(Timing::SAVED, $watch->timing);
    }

    public function test_attribute_stores_multiple_columns(): void
    {
        $watch = new Watch(['status', 'priority'], StatusChangedHandler::class);

        $this->assertEquals(['status', 'priority'], $watch->columns);
    }

    public function test_attribute_stores_custom_timing(): void
    {
        $watch = new Watch('status', StatusChangedHandler::class, timing: Timing::SAVING);

        $this->assertEquals(Timing::SAVING, $watch->timing);
    }
}
