<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Unit;

use CWAscend\LaravelColumnWatcher\Support\ColumnWatcherState;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

class ColumnWatcherStateTest extends TestCase
{
    private ColumnWatcherState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = new ColumnWatcherState;
    }

    public function test_enabled_is_true_by_default(): void
    {
        $this->assertTrue($this->state->enabled);
    }

    public function test_processing_is_empty_by_default(): void
    {
        $this->assertEmpty($this->state->processing);
    }

    public function test_key_generates_unique_identifier(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $key = $this->state->key($model, 'status');

        $this->assertEquals(
            TestModel::class.':'.$model->getKey().':status',
            $key
        );
    }

    public function test_key_differs_for_different_columns(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $statusKey = $this->state->key($model, 'status');
        $nameKey = $this->state->key($model, 'name');

        $this->assertNotEquals($statusKey, $nameKey);
    }

    public function test_key_differs_for_different_models(): void
    {
        $model1 = TestModel::create(['name' => 'Test 1']);
        $model2 = TestModel::create(['name' => 'Test 2']);

        $key1 = $this->state->key($model1, 'status');
        $key2 = $this->state->key($model2, 'status');

        $this->assertNotEquals($key1, $key2);
    }

    public function test_is_processing_returns_false_by_default(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $this->assertFalse($this->state->isProcessing($model, 'status'));
    }

    public function test_start_processing_marks_model_as_processing(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $this->state->startProcessing($model, 'status');

        $this->assertTrue($this->state->isProcessing($model, 'status'));
    }

    public function test_finish_processing_removes_processing_mark(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $this->state->startProcessing($model, 'status');
        $this->assertTrue($this->state->isProcessing($model, 'status'));

        $this->state->finishProcessing($model, 'status');
        $this->assertFalse($this->state->isProcessing($model, 'status'));
    }

    public function test_processing_is_column_specific(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $this->state->startProcessing($model, 'status');

        $this->assertTrue($this->state->isProcessing($model, 'status'));
        $this->assertFalse($this->state->isProcessing($model, 'name'));
    }

    public function test_processing_is_model_specific(): void
    {
        $model1 = TestModel::create(['name' => 'Test 1']);
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->state->startProcessing($model1, 'status');

        $this->assertTrue($this->state->isProcessing($model1, 'status'));
        $this->assertFalse($this->state->isProcessing($model2, 'status'));
    }

    public function test_can_track_multiple_models_simultaneously(): void
    {
        $model1 = TestModel::create(['name' => 'Test 1']);
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->state->startProcessing($model1, 'status');
        $this->state->startProcessing($model2, 'name');

        $this->assertTrue($this->state->isProcessing($model1, 'status'));
        $this->assertTrue($this->state->isProcessing($model2, 'name'));
        $this->assertFalse($this->state->isProcessing($model1, 'name'));
        $this->assertFalse($this->state->isProcessing($model2, 'status'));
    }

    public function test_reset_clears_all_state(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        $this->state->enabled = false;
        $this->state->startProcessing($model, 'status');

        $this->assertFalse($this->state->enabled);
        $this->assertTrue($this->state->isProcessing($model, 'status'));

        $this->state->reset();

        $this->assertTrue($this->state->enabled);
        $this->assertFalse($this->state->isProcessing($model, 'status'));
        $this->assertEmpty($this->state->processing);
    }

    public function test_reset_preserves_default_values(): void
    {
        $this->state->reset();

        $this->assertTrue($this->state->enabled);
        $this->assertEmpty($this->state->processing);
    }

    public function test_finish_processing_does_not_error_when_not_processing(): void
    {
        $model = TestModel::create(['name' => 'Test']);

        // Should not throw an exception
        $this->state->finishProcessing($model, 'status');

        $this->assertFalse($this->state->isProcessing($model, 'status'));
    }
}
