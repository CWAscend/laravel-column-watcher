<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\FakeableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;

class FakeableIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWatchers();
        FakeableHandler::reset();
        StatusChangedHandler::reset();
    }

    protected function tearDown(): void
    {
        FakeableHandler::reset();
        StatusChangedHandler::reset();
        parent::tearDown();
    }

    public function test_faking_one_handler_does_not_affect_another(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', FakeableHandler::class);
        ColumnWatcher::register(TestModel::class, 'priority', StatusChangedHandler::class);

        // Only fake FakeableHandler, not StatusChangedHandler
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft', 'priority' => 'low']);

        $model->status = 'published';
        $model->priority = 'high';
        $model->save();

        // FakeableHandler should have recorded but not executed
        $this->assertCount(1, FakeableHandler::recorded());
        $this->assertEmpty(FakeableHandler::$executed);

        // StatusChangedHandler should have actually executed
        $this->assertCount(1, StatusChangedHandler::$calls);
    }

    public function test_each_handler_maintains_separate_recorded_changes(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', FakeableHandler::class);
        ColumnWatcher::register(TestModel::class, 'priority', StatusChangedHandler::class);

        FakeableHandler::fake();
        StatusChangedHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft', 'priority' => 'low']);

        $model->status = 'published';
        $model->save();

        $model->priority = 'high';
        $model->save();

        // Each handler should only have its own changes
        $this->assertCount(1, FakeableHandler::recorded());
        $this->assertCount(1, StatusChangedHandler::recorded());

        $this->assertEquals('status', FakeableHandler::recorded()[0]->column);
        $this->assertEquals('priority', StatusChangedHandler::recorded()[0]->column);
    }

    public function test_stopping_fake_on_one_handler_does_not_affect_another(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', FakeableHandler::class);
        ColumnWatcher::register(TestModel::class, 'priority', StatusChangedHandler::class);

        FakeableHandler::fake();
        StatusChangedHandler::fake();

        // Stop faking only FakeableHandler
        FakeableHandler::stopFaking();

        $this->assertFalse(FakeableHandler::isFaking());
        $this->assertTrue(StatusChangedHandler::isFaking());
    }

    public function test_assertions_only_check_own_handler_records(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', FakeableHandler::class);
        ColumnWatcher::register(TestModel::class, 'priority', StatusChangedHandler::class);

        FakeableHandler::fake();
        StatusChangedHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft', 'priority' => 'low']);

        // Only change status (FakeableHandler)
        $model->status = 'published';
        $model->save();

        // FakeableHandler was triggered
        FakeableHandler::assertTriggered();
        FakeableHandler::assertTriggeredTimes(1);

        // StatusChangedHandler was NOT triggered (priority didn't change)
        StatusChangedHandler::assertNotTriggered();
    }
}
