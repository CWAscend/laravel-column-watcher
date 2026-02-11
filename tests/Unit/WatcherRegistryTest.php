<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Unit;

use CWAscend\LaravelColumnWatcher\Enums\Timing;
use CWAscend\LaravelColumnWatcher\Exceptions\InvalidHandlerException;
use CWAscend\LaravelColumnWatcher\Support\WatcherRegistry;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

class WatcherRegistryTest extends TestCase
{
    private WatcherRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new WatcherRegistry;
    }

    public function test_can_register_single_column_watcher(): void
    {
        $this->registry->register(
            TestModel::class,
            'status',
            StatusChangedHandler::class
        );

        $handlers = $this->registry->getHandlers(TestModel::class, Timing::SAVED, 'status');

        $this->assertCount(1, $handlers);
        $this->assertEquals(StatusChangedHandler::class, $handlers[0]);
    }

    public function test_can_register_multiple_columns(): void
    {
        $this->registry->register(
            TestModel::class,
            ['status', 'priority'],
            StatusChangedHandler::class
        );

        $statusHandlers = $this->registry->getHandlers(TestModel::class, Timing::SAVED, 'status');
        $priorityHandlers = $this->registry->getHandlers(TestModel::class, Timing::SAVED, 'priority');

        $this->assertCount(1, $statusHandlers);
        $this->assertCount(1, $priorityHandlers);
    }

    public function test_can_register_with_custom_timing(): void
    {
        $this->registry->register(
            TestModel::class,
            'status',
            StatusChangedHandler::class,
            Timing::SAVING
        );

        $savingHandlers = $this->registry->getHandlers(TestModel::class, Timing::SAVING, 'status');
        $savedHandlers = $this->registry->getHandlers(TestModel::class, Timing::SAVED, 'status');

        $this->assertCount(1, $savingHandlers);
        $this->assertCount(0, $savedHandlers);
    }

    public function test_returns_empty_array_for_unwatched_column(): void
    {
        $handlers = $this->registry->getHandlers(TestModel::class, Timing::SAVED, 'unwatched');

        $this->assertCount(0, $handlers);
    }

    public function test_can_get_watched_columns(): void
    {
        $this->registry->register(TestModel::class, 'status', StatusChangedHandler::class);
        $this->registry->register(TestModel::class, 'priority', StatusChangedHandler::class);

        $columns = $this->registry->getWatchedColumns(TestModel::class, Timing::SAVED);

        $this->assertCount(2, $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('priority', $columns);
    }

    public function test_has_watchers_returns_true_when_registered(): void
    {
        $this->registry->register(TestModel::class, 'status', StatusChangedHandler::class);

        $this->assertTrue($this->registry->hasWatchers(TestModel::class));
    }

    public function test_has_watchers_returns_false_when_not_registered(): void
    {
        $this->assertFalse($this->registry->hasWatchers(TestModel::class));
    }

    public function test_throws_exception_for_non_existent_handler(): void
    {
        $this->expectException(InvalidHandlerException::class);
        $this->expectExceptionMessage('does not exist');

        $this->registry->register(
            TestModel::class,
            'status',
            'NonExistentHandler'
        );
    }

    public function test_throws_exception_for_handler_not_extending_base_watcher(): void
    {
        $this->expectException(InvalidHandlerException::class);
        $this->expectExceptionMessage('must extend ColumnWatcher');

        $this->registry->register(
            TestModel::class,
            'status',
            TestModel::class // Not a handler
        );
    }

    public function test_clear_removes_all_watchers(): void
    {
        $this->registry->register(TestModel::class, 'status', StatusChangedHandler::class);

        $this->assertTrue($this->registry->hasWatchers(TestModel::class));

        $this->registry->clear();

        $this->assertFalse($this->registry->hasWatchers(TestModel::class));
    }
}
