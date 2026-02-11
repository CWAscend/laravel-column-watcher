<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Support\ColumnWatcherState;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;

class OctaneCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
    }

    public function test_state_is_registered_as_scoped_singleton(): void
    {
        $state1 = $this->app->make(ColumnWatcherState::class);
        $state2 = $this->app->make(ColumnWatcherState::class);

        $this->assertSame($state1, $state2);
    }

    public function test_scoped_binding_provides_fresh_state_after_flush(): void
    {
        $state = $this->app->make(ColumnWatcherState::class);
        $state->enabled = false;

        $model = TestModel::create(['name' => 'Test']);
        $state->startProcessing($model, 'status');

        $this->assertFalse($state->enabled);
        $this->assertTrue($state->isProcessing($model, 'status'));

        // Simulate Octane request boundary by flushing scoped instances
        $this->app->forgetScopedInstances();

        $freshState = $this->app->make(ColumnWatcherState::class);

        $this->assertTrue($freshState->enabled);
        $this->assertFalse($freshState->isProcessing($model, 'status'));
        $this->assertNotSame($state, $freshState);
    }

    public function test_disable_state_does_not_persist_after_scope_flush(): void
    {
        ColumnWatcher::disable();
        $this->assertFalse(ColumnWatcher::isEnabled());

        // Simulate Octane request boundary
        $this->app->forgetScopedInstances();

        $this->assertTrue(ColumnWatcher::isEnabled());
    }

    public function test_processing_state_does_not_persist_after_scope_flush(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        ColumnWatcher::startProcessing($model, 'status');
        $this->assertTrue(ColumnWatcher::isProcessing($model, 'status'));

        // Simulate Octane request boundary
        $this->app->forgetScopedInstances();

        $this->assertFalse(ColumnWatcher::isProcessing($model, 'status'));
    }

    public function test_watchers_work_correctly_after_scope_flush(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        // First "request"
        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        StatusChangedHandler::reset();

        // Simulate Octane request boundary
        $this->app->forgetScopedInstances();

        // Second "request" - should work identically
        $model->status = 'archived';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
    }

    public function test_disabled_state_in_one_request_does_not_affect_next(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // First "request" - disable watchers
        ColumnWatcher::disable();
        $model->status = 'pending';
        $model->save();

        $this->assertCount(0, StatusChangedHandler::$calls);

        // Simulate Octane request boundary
        $this->app->forgetScopedInstances();
        StatusChangedHandler::reset();

        // Second "request" - watchers should be enabled again
        $this->assertTrue(ColumnWatcher::isEnabled());

        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
    }

    public function test_incomplete_processing_does_not_block_next_request(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        // First "request" - start processing but never finish (simulating exception)
        ColumnWatcher::startProcessing($model, 'status');
        $this->assertTrue(ColumnWatcher::isProcessing($model, 'status'));

        // Simulate Octane request boundary (no finishProcessing called)
        $this->app->forgetScopedInstances();

        // Second "request" - should not be marked as processing
        $this->assertFalse(ColumnWatcher::isProcessing($model, 'status'));
    }

    public function test_multiple_models_processing_state_cleared_on_flush(): void
    {
        $model1 = TestModel::create(['name' => 'Test 1']);
        $model2 = TestModel::create(['name' => 'Test 2']);
        $model3 = TestModel::create(['name' => 'Test 3']);

        ColumnWatcher::startProcessing($model1, 'status');
        ColumnWatcher::startProcessing($model2, 'name');
        ColumnWatcher::startProcessing($model3, 'priority');

        $this->assertTrue(ColumnWatcher::isProcessing($model1, 'status'));
        $this->assertTrue(ColumnWatcher::isProcessing($model2, 'name'));
        $this->assertTrue(ColumnWatcher::isProcessing($model3, 'priority'));

        // Simulate Octane request boundary
        $this->app->forgetScopedInstances();

        $this->assertFalse(ColumnWatcher::isProcessing($model1, 'status'));
        $this->assertFalse(ColumnWatcher::isProcessing($model2, 'name'));
        $this->assertFalse(ColumnWatcher::isProcessing($model3, 'priority'));
    }

    public function test_state_accessor_returns_same_instance_within_request(): void
    {
        $state1 = $this->app->make(ColumnWatcherState::class);
        $state2 = $this->app->make(ColumnWatcherState::class);

        $state1->enabled = false;

        $this->assertFalse($state2->enabled);
        $this->assertSame($state1, $state2);
    }

    public function test_config_based_disable_persists_across_flushes(): void
    {
        config(['column-watcher.enabled' => false]);

        $this->assertFalse(ColumnWatcher::isEnabled());

        // Simulate Octane request boundary
        $this->app->forgetScopedInstances();

        // Config-based disabling should persist (it's not in scoped state)
        $this->assertFalse(ColumnWatcher::isEnabled());

        config(['column-watcher.enabled' => true]);

        $this->assertTrue(ColumnWatcher::isEnabled());
    }
}
