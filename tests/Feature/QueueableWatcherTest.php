<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Illuminate\Support\Facades\Queue;

class QueueableWatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        DirectQueueableHandler::reset();
    }

    public function test_queueable_watcher_dispatches_directly_without_wrapper(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // Should dispatch the handler directly, not wrapped in HandleColumnChange
        Queue::assertPushed(DirectQueueableHandler::class, function ($job) use ($model) {
            return $job->model->is($model)
                && $job->column === 'status'
                && $job->oldValue === 'draft'
                && $job->newValue === 'published';
        });

        // Handler is dispatched directly (not wrapped)
    }

    public function test_queueable_watcher_executes_when_processed(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // Manually create and execute the job
        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        $this->assertCount(1, DirectQueueableHandler::$executed);
        $this->assertEquals('published', DirectQueueableHandler::$executed[0]['new_value']);
    }

    public function test_queueable_watcher_has_correct_display_name(): void
    {
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $this->assertEquals(DirectQueueableHandler::class, $job->displayName());
    }

    public function test_queueable_watcher_has_correct_tags(): void
    {
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $tags = $job->tags();

        $this->assertContains(DirectQueueableHandler::class, $tags);
        $this->assertContains(TestModel::class.':'.$model->id, $tags);
        $this->assertContains('column:status', $tags);
    }

    public function test_queueable_watcher_can_be_faked(): void
    {
        DirectQueueableHandler::fake();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // Manually process the job (simulating queue worker)
        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        // Should record but not execute
        DirectQueueableHandler::assertTriggered();
        $this->assertEmpty(DirectQueueableHandler::$executed);
    }

    public function test_queueable_watcher_assert_triggered_with_callback(): void
    {
        DirectQueueableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        DirectQueueableHandler::assertTriggered(
            fn ($change) => $change->newValue === 'published'
        );
    }

    public function test_queueable_watcher_assert_triggered_for_column(): void
    {
        DirectQueueableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        DirectQueueableHandler::assertTriggeredForColumn('status');
    }

    public function test_queueable_watcher_assert_triggered_with_values(): void
    {
        DirectQueueableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        DirectQueueableHandler::assertTriggeredWithValues('draft', 'published');
    }

    public function test_queueable_watcher_assert_triggered_times(): void
    {
        DirectQueueableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job1 = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'pending',
        );

        $job2 = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'pending',
            newValue: 'published',
        );

        $job1->handle();
        $job2->handle();

        DirectQueueableHandler::assertTriggeredTimes(2);
    }

    public function test_queueable_watcher_assert_not_triggered(): void
    {
        DirectQueueableHandler::fake();

        DirectQueueableHandler::assertNotTriggered();
    }
}
