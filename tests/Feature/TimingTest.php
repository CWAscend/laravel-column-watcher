<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Feature;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Enums\Timing;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModelWithSavingTiming;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

class TimingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
    }

    public function test_saving_timing_triggers_before_save(): void
    {
        $model = TestModelWithSavingTiming::create(['name' => 'Test', 'status' => 'draft']);

        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertEquals('draft', StatusChangedHandler::$calls[0]['old_value']);
        $this->assertEquals('published', StatusChangedHandler::$calls[0]['new_value']);
    }

    public function test_can_have_both_saving_and_saved_handlers(): void
    {
        // Register handler for saved timing via static method
        ColumnWatcher::register(TestModelWithSavingTiming::class, 'priority', StatusChangedHandler::class, Timing::SAVED);

        $model = TestModelWithSavingTiming::create(['name' => 'Test', 'status' => 'draft', 'priority' => 'normal']);

        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->priority = 'high';
        $model->save();

        // Should have 2 calls: one from attribute (saving) for status, one from static (saved) for priority
        $this->assertCount(2, StatusChangedHandler::$calls);
    }
}
