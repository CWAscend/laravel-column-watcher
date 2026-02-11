<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Feature;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\LoopingHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

class InfiniteLoopProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWatchers();
        LoopingHandler::reset();
    }

    protected function tearDown(): void
    {
        LoopingHandler::reset();
        parent::tearDown();
    }

    public function test_prevents_infinite_loop_when_handler_saves_same_model(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', LoopingHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // This would cause infinite recursion without protection
        $model->status = 'published';
        $model->save();

        // Handler should only be called once, not infinitely
        $this->assertEquals(1, LoopingHandler::$callCount);

        // But the model should have been modified by the handler
        $model->refresh();
        $this->assertEquals('published_modified', $model->status);
    }

    public function test_allows_subsequent_changes_after_processing_completes(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', LoopingHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // First change
        $model->status = 'pending';
        $model->save();
        $this->assertEquals(1, LoopingHandler::$callCount);

        // Second change (should trigger again since processing is complete)
        $model->status = 'approved';
        $model->save();
        $this->assertEquals(2, LoopingHandler::$callCount);
    }
}
