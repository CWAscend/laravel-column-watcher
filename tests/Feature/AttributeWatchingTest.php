<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\Tests\Fixtures\MultiColumnHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModelWithAttribute;
use Ascend\LaravelColumnWatcher\Tests\TestCase;

class AttributeWatchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
        MultiColumnHandler::reset();
    }

    public function test_handler_is_called_when_watched_column_changes(): void
    {
        $model = TestModelWithAttribute::create(['name' => 'Test', 'status' => 'draft']);

        // Reset after create to only count update changes
        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertEquals('status', StatusChangedHandler::$calls[0]['column']);
        $this->assertEquals('draft', StatusChangedHandler::$calls[0]['old_value']);
        $this->assertEquals('published', StatusChangedHandler::$calls[0]['new_value']);
    }

    public function test_handler_is_not_called_when_column_unchanged(): void
    {
        $model = TestModelWithAttribute::create(['name' => 'Test', 'status' => 'draft']);

        // Reset after create
        StatusChangedHandler::reset();
        MultiColumnHandler::reset();

        $model->name = 'Updated Name';
        $model->save();

        $this->assertCount(1, MultiColumnHandler::$calls); // name is watched by MultiColumnHandler
        $this->assertCount(0, StatusChangedHandler::$calls); // status not changed
    }

    public function test_multi_column_handler_called_for_each_changed_column(): void
    {
        $model = TestModelWithAttribute::create(['name' => 'Test', 'priority' => 'normal']);

        // Reset after create
        MultiColumnHandler::reset();

        $model->name = 'Updated';
        $model->priority = 'high';
        $model->save();

        $this->assertCount(2, MultiColumnHandler::$calls);

        $columns = array_column(MultiColumnHandler::$calls, 'column');
        $this->assertContains('name', $columns);
        $this->assertContains('priority', $columns);
    }

    public function test_handler_receives_correct_model_instance(): void
    {
        $model = TestModelWithAttribute::create(['name' => 'Test', 'status' => 'draft']);

        // Reset after create
        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->save();

        $this->assertEquals($model->id, StatusChangedHandler::$calls[0]['model_id']);
    }

    public function test_handler_is_not_called_on_create_unless_opted_in(): void
    {
        StatusChangedHandler::reset();

        TestModelWithAttribute::create(['name' => 'Test', 'status' => 'active']);

        // SAVED fires after updates only; see CreateAndUpdateTest for Timing::CREATED.
        $this->assertCount(0, StatusChangedHandler::$calls);
    }
}
