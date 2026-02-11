<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\Tests\Fixtures\QueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class QueueableHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        QueueableHandler::reset();
        StatusChangedHandler::reset();
    }

    public function test_queueable_handler_is_dispatched_to_queue(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', QueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // Handler is dispatched directly (no wrapper job)
        Queue::assertPushed(QueueableHandler::class, function ($job) use ($model) {
            return $job->model->is($model)
                && $job->column === 'status'
                && $job->oldValue === 'draft'
                && $job->newValue === 'published';
        });
    }

    public function test_non_queueable_handler_runs_synchronously(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // No job dispatched for sync handlers
        Queue::assertNotPushed(QueueableHandler::class);
        Queue::assertNotPushed(StatusChangedHandler::class);

        // Handler was executed synchronously
        $this->assertCount(1, StatusChangedHandler::$calls);
    }

    public function test_queueable_handler_uses_configured_queue(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', QueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        Queue::assertPushedOn('watchers', QueueableHandler::class);
    }

    public function test_handler_executes_when_processed(): void
    {
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // Manually create and execute the handler
        $job = new QueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        $this->assertCount(1, QueueableHandler::$executed);
        $this->assertEquals('published', QueueableHandler::$executed[0]['new_value']);
    }

    public function test_handler_has_correct_display_name(): void
    {
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new QueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $this->assertEquals(QueueableHandler::class, $job->displayName());
    }

    public function test_handler_has_correct_tags(): void
    {
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new QueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $tags = $job->tags();

        $this->assertContains(QueueableHandler::class, $tags);
        $this->assertContains(TestModel::class.':'.$model->id, $tags);
        $this->assertContains('column:status', $tags);
    }

    public function test_queueable_handler_dispatches_after_transaction_commits(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', QueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        DB::transaction(function () use ($model) {
            $model->status = 'published';
            $model->save();

            // Inside transaction, job should not be dispatched yet
            Queue::assertNothingPushed();
        });

        // After transaction commits, job should be dispatched
        Queue::assertPushed(QueueableHandler::class, function ($job) use ($model) {
            return $job->model->is($model)
                && $job->column === 'status'
                && $job->oldValue === 'draft'
                && $job->newValue === 'published';
        });
    }

    public function test_queueable_handler_not_dispatched_when_transaction_rolls_back(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', QueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        try {
            DB::transaction(function () use ($model) {
                $model->status = 'published';
                $model->save();

                throw new \Exception('Rollback transaction');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // After rollback, job should NOT be dispatched
        Queue::assertNothingPushed();
    }
}
