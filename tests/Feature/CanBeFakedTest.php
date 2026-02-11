<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Feature;

use CWAscend\LaravelColumnWatcher\Tests\Fixtures\FakeableHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;
use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use PHPUnit\Framework\AssertionFailedError;

class CanBeFakedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        FakeableHandler::reset();

        ColumnWatcher::register(TestModel::class, 'status', FakeableHandler::class);
    }

    protected function tearDown(): void
    {
        FakeableHandler::reset();

        parent::tearDown();
    }

    public function test_fake_prevents_handler_execution(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // Handler was NOT actually executed
        $this->assertEmpty(FakeableHandler::$executed);

        // But the call was recorded
        $this->assertCount(1, FakeableHandler::recorded());
    }

    public function test_without_fake_handler_executes_normally(): void
    {
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        // Handler WAS executed
        $this->assertCount(1, FakeableHandler::$executed);
    }

    public function test_assert_triggered_passes_when_triggered(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        FakeableHandler::assertTriggered();
    }

    public function test_assert_triggered_fails_when_not_triggered(): void
    {
        FakeableHandler::fake();

        $this->expectException(AssertionFailedError::class);

        FakeableHandler::assertTriggered();
    }

    public function test_assert_not_triggered_passes_when_not_triggered(): void
    {
        FakeableHandler::fake();

        FakeableHandler::assertNotTriggered();
    }

    public function test_assert_not_triggered_fails_when_triggered(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->expectException(AssertionFailedError::class);

        FakeableHandler::assertNotTriggered();
    }

    public function test_assert_triggered_times(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $model->status = 'pending';
        $model->save();

        $model->status = 'published';
        $model->save();

        FakeableHandler::assertTriggeredTimes(2);
    }

    public function test_assert_triggered_with_callback(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        FakeableHandler::assertTriggered(
            fn ($change) => $change->newValue === 'published'
        );
    }

    public function test_assert_triggered_with_callback_fails_when_no_match(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->expectException(AssertionFailedError::class);

        FakeableHandler::assertTriggered(
            fn ($change) => $change->newValue === 'archived'
        );
    }

    public function test_assert_triggered_for_column(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        FakeableHandler::assertTriggeredForColumn('status');
    }

    public function test_assert_triggered_with_values(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        FakeableHandler::assertTriggeredWithValues('draft', 'published');
    }

    public function test_stop_faking_clears_state(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertNotEmpty(FakeableHandler::recorded());

        FakeableHandler::stopFaking();

        $this->assertEmpty(FakeableHandler::recorded());
        $this->assertFalse(FakeableHandler::isFaking());
    }
}
