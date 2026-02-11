<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Feature;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Enums\Timing;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

class StaticRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
    }

    public function test_can_register_watcher_via_static_method(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertEquals('published', StatusChangedHandler::$calls[0]['new_value']);
    }

    public function test_can_register_multiple_columns_via_static_method(): void
    {
        ColumnWatcher::register(TestModel::class, ['status', 'priority'], StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft', 'priority' => 'normal']);
        $model->status = 'published';
        $model->priority = 'high';
        $model->save();

        $this->assertCount(2, StatusChangedHandler::$calls);
    }

    public function test_can_register_with_saving_timing(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::SAVING);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // Reset after create to only count update changes
        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
    }

    public function test_watchers_from_different_models_are_isolated(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        // Create a different model without registration
        $model = TestModel::create(['name' => 'Test']);
        $model->name = 'Updated';
        $model->save();

        // Only status is watched, not name
        $this->assertCount(0, StatusChangedHandler::$calls);
    }
}
