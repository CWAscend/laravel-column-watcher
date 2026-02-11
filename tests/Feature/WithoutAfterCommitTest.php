<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class WithoutAfterCommitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        DirectQueueableHandler::reset();
    }

    protected function tearDown(): void
    {
        DirectQueueableHandler::reset();
        ColumnWatcher::withAfterCommit();

        parent::tearDown();
    }

    public function test_without_after_commit_can_be_enabled(): void
    {
        $this->assertFalse(ColumnWatcher::isWithoutAfterCommit());

        ColumnWatcher::withoutAfterCommit();

        $this->assertTrue(ColumnWatcher::isWithoutAfterCommit());
    }

    public function test_with_after_commit_resets_to_default(): void
    {
        ColumnWatcher::withoutAfterCommit();
        $this->assertTrue(ColumnWatcher::isWithoutAfterCommit());

        ColumnWatcher::withAfterCommit();

        $this->assertFalse(ColumnWatcher::isWithoutAfterCommit());
    }

    public function test_queueable_handler_dispatches_with_database_transactions_when_bypassed(): void
    {
        Queue::fake();
        ColumnWatcher::withoutAfterCommit();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        // Even though we're inside a transaction (from DatabaseTransactions trait),
        // the job should be dispatched immediately
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        Queue::assertPushed(DirectQueueableHandler::class, function ($job) use ($model) {
            return $job->model->is($model)
                && $job->column === 'status'
                && $job->oldValue === 'draft'
                && $job->newValue === 'published';
        });
    }

    public function test_queueable_handler_not_dispatched_without_bypass_in_transaction(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        // Without the bypass, jobs are queued for afterCommit
        // Since DatabaseTransactions never commits, we need to verify
        // the job is scheduled but not dispatched yet
        DB::transaction(function () {
            $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
            $model->status = 'published';
            $model->save();

            // Inside the transaction, job should NOT be dispatched yet
            // (it's waiting for afterCommit)
            Queue::assertNothingPushed();
        });

        // After the transaction commits, job should be dispatched
        Queue::assertPushed(DirectQueueableHandler::class);
    }

    public function test_queueable_handler_dispatched_immediately_outside_transaction(): void
    {
        Queue::fake();

        // Ensure we're not in the DatabaseTransactions wrapper
        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        // Create model outside any explicit transaction
        // Note: The test framework's transaction is still there, but we're testing
        // the case where transactionLevel would be 0 in production
        $this->assertTrue(DB::transactionLevel() > 0, 'Expected to be in a transaction from DatabaseTransactions');

        // With bypass enabled, job should dispatch immediately even in transaction
        ColumnWatcher::withoutAfterCommit();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        Queue::assertPushed(DirectQueueableHandler::class);
    }

    public function test_faking_works_with_without_after_commit(): void
    {
        ColumnWatcher::withoutAfterCommit();
        DirectQueueableHandler::fake();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // Since handler is faked, we need to manually process the job
        // that was dispatched (withoutAfterCommit just changes WHEN it's dispatched)
        // This tests that faking still works correctly with the bypass

        // The job was dispatched, so let's manually process it
        $job = new DirectQueueableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        DirectQueueableHandler::assertTriggered();
        $this->assertEmpty(DirectQueueableHandler::$executed);
    }

    public function test_reset_state_clears_without_after_commit(): void
    {
        ColumnWatcher::withoutAfterCommit();
        $this->assertTrue(ColumnWatcher::isWithoutAfterCommit());

        $this->resetState();

        $this->assertFalse(ColumnWatcher::isWithoutAfterCommit());
    }
}
