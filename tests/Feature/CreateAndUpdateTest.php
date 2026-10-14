<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Ascend\LaravelColumnWatcher\Exceptions\InvalidTimingException;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\MultiColumnHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\NameChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModelWithAttribute;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModelWithCreateUpdateTimings;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function test_saved_does_not_fire_on_create(): void
    {
        TestModelWithAttribute::create(['name' => 'Test', 'status' => 'active']);

        $this->assertCount(0, StatusChangedHandler::$calls);
    }

    public function test_saving_fires_on_create_and_update(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::SAVING);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertCount(2, StatusChangedHandler::$calls);
        $this->assertNull(StatusChangedHandler::$calls[0]['old_value']);
    }

    public function test_created_fires_on_create_with_a_null_old_value(): void
    {
        $model = TestModelWithCreateUpdateTimings::create(['name' => 'Test', 'priority' => 'high']);

        $this->assertCount(1, MultiColumnHandler::$calls);
        $this->assertSame($model->id, MultiColumnHandler::$calls[0]['model_id']);
        $this->assertNull(MultiColumnHandler::$calls[0]['old_value']);
        $this->assertSame('high', MultiColumnHandler::$calls[0]['new_value']);
    }

    public function test_created_does_not_fire_for_a_column_that_was_not_set(): void
    {
        TestModelWithCreateUpdateTimings::create(['name' => 'Test']);

        $this->assertCount(0, MultiColumnHandler::$calls);
    }

    public function test_created_does_not_fire_on_later_saves_of_the_same_instance(): void
    {
        $model = TestModelWithCreateUpdateTimings::create(['name' => 'Test', 'priority' => 'high']);

        // wasRecentlyCreated is still true on this instance, but this save is an update.
        $model->priority = 'low';
        $model->save();

        $this->assertCount(1, MultiColumnHandler::$calls);
    }

    public function test_created_and_updated_together_fire_once_per_save(): void
    {
        $model = TestModelWithCreateUpdateTimings::create(['name' => 'Test', 'status' => 'draft']);

        $this->assertCount(1, StatusChangedHandler::$calls);

        $model->status = 'published';
        $model->save();

        $this->assertCount(2, StatusChangedHandler::$calls);
        $this->assertSame('draft', StatusChangedHandler::$calls[1]['old_value']);
        $this->assertSame('published', StatusChangedHandler::$calls[1]['new_value']);
    }

    public function test_updating_fires_before_an_update_but_not_on_create(): void
    {
        $model = TestModelWithCreateUpdateTimings::create(['name' => 'Test']);

        $this->assertCount(0, NameChangedHandler::$calls);

        $model->name = 'Renamed';
        $model->save();

        $this->assertCount(1, NameChangedHandler::$calls);
        $this->assertSame('Test', NameChangedHandler::$calls[0]['old_value']);
    }

    public function test_creating_runs_before_the_row_is_inserted(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::CREATING);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertNull(StatusChangedHandler::$calls[0]['model_id']);
        $this->assertSame('draft', StatusChangedHandler::$calls[0]['new_value']);
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

    public function test_queued_created_watcher_is_dispatched_with_the_persisted_model(): void
    {
        Queue::fake();

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class, Timing::CREATED);

        $model = TestModel::create(['name' => 'Test', 'status' => 'active']);

        Queue::assertPushed(DirectQueueableHandler::class, fn (DirectQueueableHandler $job) => $job->model->is($model)
            && $job->model->getKey() !== null
            && $job->oldValue === null
            && $job->newValue === 'active');
    }

    #[DataProvider('beforeSaveTimings')]
    public function test_queued_handlers_cannot_use_a_before_save_timing(Timing $timing): void
    {
        $this->expectException(InvalidTimingException::class);

        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class, $timing);
    }

    public static function beforeSaveTimings(): array
    {
        return [
            'saving' => [Timing::SAVING],
            'creating' => [Timing::CREATING],
            'updating' => [Timing::UPDATING],
        ];
    }

    #[DataProvider('overlappingTimings')]
    public function test_registering_one_handler_at_overlapping_timings_throws(Timing $first, Timing $second): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, $first);

        $this->expectException(InvalidTimingException::class);

        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, $second);
    }

    public static function overlappingTimings(): array
    {
        return [
            'saved then updated' => [Timing::SAVED, Timing::UPDATED],
            'updated then saved' => [Timing::UPDATED, Timing::SAVED],
            'saving then creating' => [Timing::SAVING, Timing::CREATING],
            'updating then saving' => [Timing::UPDATING, Timing::SAVING],
        ];
    }

    public function test_one_handler_can_watch_non_overlapping_timings(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::SAVED);
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::CREATED);
        ColumnWatcher::register(TestModel::class, 'priority', StatusChangedHandler::class, Timing::UPDATED);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertCount(2, StatusChangedHandler::$calls);
    }

    public function test_watcher_list_shows_the_new_timings(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::CREATED);
        ColumnWatcher::register(TestModel::class, 'status', MultiColumnHandler::class, Timing::UPDATING);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('TestModel.status (CREATED)')
            ->expectsOutputToContain('TestModel.status (UPDATING)')
            ->assertSuccessful();
    }
}
