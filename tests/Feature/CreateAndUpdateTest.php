<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Ascend\LaravelColumnWatcher\Exceptions\InvalidOperationException;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\MultiColumnHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\NameChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModelWithAttribute;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModelWithOperationFilters;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class CreateAndUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
        MultiColumnHandler::reset();
        NameChangedHandler::reset();
        DirectQueueableHandler::reset();
    }

    public function test_saved_watcher_does_not_fire_on_create_by_default(): void
    {
        TestModelWithAttribute::create(['name' => 'Test', 'status' => 'active']);

        $this->assertCount(0, StatusChangedHandler::$calls);
    }

    public function test_saving_watcher_fires_on_create_by_default(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::SAVING);

        TestModel::create(['name' => 'Test', 'status' => 'active']);

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertNull(StatusChangedHandler::$calls[0]['old_value']);
    }

    public function test_opted_in_saved_watcher_fires_on_create_with_a_null_old_value(): void
    {
        $model = TestModelWithOperationFilters::create(['name' => 'Test', 'status' => 'active']);

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertSame($model->id, StatusChangedHandler::$calls[0]['model_id']);
        $this->assertNull(StatusChangedHandler::$calls[0]['old_value']);
        $this->assertSame('active', StatusChangedHandler::$calls[0]['new_value']);
    }

    public function test_opted_in_saved_watcher_still_fires_on_update(): void
    {
        $model = TestModelWithOperationFilters::create(['name' => 'Test', 'status' => 'draft']);
        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertSame('draft', StatusChangedHandler::$calls[0]['old_value']);
    }

    public function test_opted_in_saved_watcher_does_not_fire_on_create_for_a_column_that_was_not_set(): void
    {
        TestModelWithOperationFilters::create(['name' => 'Test']);

        $this->assertCount(0, StatusChangedHandler::$calls);
    }

    public function test_create_only_watcher_skips_later_saves_of_the_same_instance(): void
    {
        $model = TestModelWithOperationFilters::create(['name' => 'Test', 'priority' => 'high']);

        $this->assertCount(1, MultiColumnHandler::$calls);

        // wasRecentlyCreated is still true on this instance, but this save is an update.
        $model->priority = 'low';
        $model->save();

        $this->assertCount(1, MultiColumnHandler::$calls);
    }

    public function test_saving_watcher_can_opt_out_of_create(): void
    {
        $model = TestModelWithOperationFilters::create(['name' => 'Test']);

        $this->assertCount(0, NameChangedHandler::$calls);

        $model->name = 'Renamed';
        $model->save();

        $this->assertCount(1, NameChangedHandler::$calls);
    }

    public function test_a_save_with_no_changes_does_not_refire_the_previous_saves_changes(): void
    {
        $model = TestModelWithAttribute::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        StatusChangedHandler::reset();

        // Laravel still fires `saved` for a clean model, and only refreshes wasChanged() on a real update.
        $model->save();

        $this->assertCount(0, StatusChangedHandler::$calls);
    }

    public function test_opted_in_queued_watcher_on_create_is_dispatched_with_the_persisted_model(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class, onCreate: true);

        $model = TestModel::create(['name' => 'Test', 'status' => 'active']);

        Queue::assertPushed(DirectQueueableHandler::class, fn (DirectQueueableHandler $job) => $job->model->is($model)
            && $job->model->getKey() !== null
            && $job->oldValue === null
            && $job->newValue === 'active');
    }

    public function test_registering_with_neither_create_nor_update_throws(): void
    {
        $this->expectException(InvalidOperationException::class);

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, onUpdate: false);
    }

    public function test_watcher_list_shows_operation_restrictions(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);
        ColumnWatcher::register(TestModel::class, 'priority', MultiColumnHandler::class, onCreate: true, onUpdate: false);
        ColumnWatcher::register(TestModel::class, 'name', NameChangedHandler::class, onCreate: true);

        $this->artisan('watcher:list')
            ->expectsOutputToContain(StatusChangedHandler::class.' [update only]')
            ->expectsOutputToContain(MultiColumnHandler::class.' [create only]')
            ->doesntExpectOutputToContain(NameChangedHandler::class.' [')
            ->assertSuccessful();
    }
}
