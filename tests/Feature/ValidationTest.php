<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Ascend\LaravelColumnWatcher\Exceptions\InvalidTimingException;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;

class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWatchers();
    }

    public function test_accepts_timing_enum_saving(): void
    {
        ColumnWatcher::register(
            TestModel::class,
            'status',
            StatusChangedHandler::class,
            Timing::SAVING
        );

        $this->assertTrue(true);
    }

    public function test_accepts_timing_enum_saved(): void
    {
        ColumnWatcher::register(
            TestModel::class,
            'status',
            StatusChangedHandler::class,
            Timing::SAVED
        );

        $this->assertTrue(true);
    }

    public function test_throws_exception_for_queueable_handler_with_saving_timing(): void
    {
        $this->expectException(InvalidTimingException::class);
        $this->expectExceptionMessage('implements ShouldQueue but uses Timing::SAVING');

        ColumnWatcher::register(
            TestModel::class,
            'status',
            DirectQueueableHandler::class,
            Timing::SAVING
        );
    }

    public function test_allows_queueable_handler_with_saved_timing(): void
    {
        ColumnWatcher::register(
            TestModel::class,
            'status',
            DirectQueueableHandler::class,
            Timing::SAVED
        );

        $this->assertTrue(true);
    }
}
