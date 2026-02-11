<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Events\WatcherFailed;
use Ascend\LaravelColumnWatcher\Events\WatcherStarted;
use Ascend\LaravelColumnWatcher\Events\WatcherSucceeded;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\QueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Exception;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class WatcherEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
    }

    public function test_watcher_started_event_is_dispatched(): void
    {
        Event::fake([WatcherStarted::class]);

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        Event::assertDispatched(WatcherStarted::class, function (WatcherStarted $event) use ($model) {
            return $event->watcher instanceof StatusChangedHandler
                && $event->watcher->model->is($model)
                && $event->watcher->column === 'status';
        });
    }

    public function test_watcher_succeeded_event_is_dispatched(): void
    {
        Event::fake([WatcherSucceeded::class]);

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        Event::assertDispatched(WatcherSucceeded::class, function (WatcherSucceeded $event) use ($model) {
            return $event->watcher instanceof StatusChangedHandler
                && $event->watcher->model->is($model)
                && $event->watcher->column === 'status';
        });
    }

    public function test_watcher_failed_event_is_dispatched_on_exception(): void
    {
        Event::fake([WatcherFailed::class]);

        ColumnWatcher::register(TestModel::class, 'status', FailingHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        try {
            $model->status = 'published';
            $model->save();
        } catch (Exception) {
            // Expected
        }

        Event::assertDispatched(WatcherFailed::class, function (WatcherFailed $event) use ($model) {
            return $event->watcher instanceof FailingHandler
                && $event->watcher->model->is($model)
                && $event->watcher->column === 'status'
                && $event->exception->getMessage() === 'Watcher failed';
        });
    }

    public function test_watcher_succeeded_not_dispatched_on_exception(): void
    {
        Event::fake([WatcherSucceeded::class, WatcherFailed::class]);

        ColumnWatcher::register(TestModel::class, 'status', FailingHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        try {
            $model->status = 'published';
            $model->save();
        } catch (Exception) {
            // Expected
        }

        Event::assertNotDispatched(WatcherSucceeded::class);
        Event::assertDispatched(WatcherFailed::class);
    }

    public function test_exception_is_rethrown_after_failed_event(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', FailingHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Watcher failed');

        $model->status = 'published';
        $model->save();
    }

    public function test_events_contain_old_and_new_values(): void
    {
        Event::fake([WatcherStarted::class]);

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        Event::assertDispatched(WatcherStarted::class, function (WatcherStarted $event) {
            return $event->watcher->oldValue === 'draft'
                && $event->watcher->newValue === 'published';
        });
    }

    public function test_multiple_watchers_dispatch_multiple_events(): void
    {
        Event::fake([WatcherStarted::class, WatcherSucceeded::class]);

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);
        ColumnWatcher::register(TestModel::class, 'name', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->name = 'Updated';
        $model->save();

        Event::assertDispatchedTimes(WatcherStarted::class, 2);
        Event::assertDispatchedTimes(WatcherSucceeded::class, 2);
    }

    public function test_processing_state_cleared_even_on_failure(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', FailingHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        try {
            $model->status = 'published';
            $model->save();
        } catch (Exception) {
            // Expected
        }

        $this->assertFalse(ColumnWatcher::isProcessing($model, 'status'));
    }

    public function test_queued_watcher_fires_events_when_processed(): void
    {
        Queue::fake();
        Event::fake([WatcherStarted::class, WatcherSucceeded::class]);

        ColumnWatcher::register(TestModel::class, 'status', QueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // Events should not fire yet (job is queued, not processed)
        Event::assertNotDispatched(WatcherStarted::class);
        Event::assertNotDispatched(WatcherSucceeded::class);

        // Get the queued job and process it
        Queue::assertPushed(QueueableHandler::class, function (QueueableHandler $job) use ($model) {
            // Stop faking events so we can test them firing
            Event::swap(new \Illuminate\Events\Dispatcher);
            Event::fake([WatcherStarted::class, WatcherSucceeded::class]);

            $job->handle();

            Event::assertDispatched(WatcherStarted::class);
            Event::assertDispatched(WatcherSucceeded::class);

            return true;
        });
    }
}

class FailingHandler extends ColumnWatcher
{
    protected function execute(\Ascend\LaravelColumnWatcher\Data\ColumnChange $change): void
    {
        throw new Exception('Watcher failed');
    }
}
