<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Feature;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Enums\Timing;
use CWAscend\LaravelColumnWatcher\Exceptions\InvalidTimingException;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

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
