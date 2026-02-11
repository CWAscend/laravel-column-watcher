<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\TestCase;

class DisableEnableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        StatusChangedHandler::reset();
        ColumnWatcher::enable();
    }

    protected function tearDown(): void
    {
        ColumnWatcher::enable();

        parent::tearDown();
    }

    public function test_watchers_are_enabled_by_default(): void
    {
        $this->assertTrue(ColumnWatcher::isEnabled());
    }

    public function test_disable_prevents_watchers_from_running(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        ColumnWatcher::disable();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'published';
        $model->save();

        $this->assertCount(0, StatusChangedHandler::$calls);
    }

    public function test_enable_allows_watchers_to_run_again(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        ColumnWatcher::disable();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        $model->status = 'pending';
        $model->save();

        $this->assertCount(0, StatusChangedHandler::$calls);

        ColumnWatcher::enable();

        $model->status = 'published';
        $model->save();

        $this->assertCount(1, StatusChangedHandler::$calls);
        $this->assertEquals('published', StatusChangedHandler::$calls[0]['new_value']);
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        $this->assertTrue(ColumnWatcher::isEnabled());

        ColumnWatcher::disable();

        $this->assertFalse(ColumnWatcher::isEnabled());
    }

    public function test_is_enabled_returns_true_after_enable(): void
    {
        ColumnWatcher::disable();
        $this->assertFalse(ColumnWatcher::isEnabled());

        ColumnWatcher::enable();
        $this->assertTrue(ColumnWatcher::isEnabled());
    }

    public function test_is_enabled_respects_config_setting(): void
    {
        config(['column-watcher.enabled' => false]);

        $this->assertFalse(ColumnWatcher::isEnabled());

        config(['column-watcher.enabled' => true]);

        $this->assertTrue(ColumnWatcher::isEnabled());
    }

    public function test_is_enabled_requires_both_static_and_config_to_be_true(): void
    {
        // Both enabled
        config(['column-watcher.enabled' => true]);
        ColumnWatcher::enable();
        $this->assertTrue(ColumnWatcher::isEnabled());

        // Static disabled, config enabled
        ColumnWatcher::disable();
        $this->assertFalse(ColumnWatcher::isEnabled());

        // Static enabled, config disabled
        ColumnWatcher::enable();
        config(['column-watcher.enabled' => false]);
        $this->assertFalse(ColumnWatcher::isEnabled());

        // Both disabled
        ColumnWatcher::disable();
        $this->assertFalse(ColumnWatcher::isEnabled());
    }

    public function test_disable_affects_multiple_watchers(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);
        ColumnWatcher::register(TestModel::class, 'priority', StatusChangedHandler::class);

        ColumnWatcher::disable();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft', 'priority' => 'normal']);
        $model->status = 'published';
        $model->priority = 'high';
        $model->save();

        $this->assertCount(0, StatusChangedHandler::$calls);
    }

    public function test_can_toggle_watchers_during_operation(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // First change with watchers enabled
        StatusChangedHandler::reset();
        $model->status = 'pending';
        $model->save();
        $this->assertCount(1, StatusChangedHandler::$calls);

        // Second change with watchers disabled
        StatusChangedHandler::reset();
        ColumnWatcher::disable();
        $model->status = 'processing';
        $model->save();
        $this->assertCount(0, StatusChangedHandler::$calls);

        // Third change with watchers re-enabled
        StatusChangedHandler::reset();
        ColumnWatcher::enable();
        $model->status = 'published';
        $model->save();
        $this->assertCount(1, StatusChangedHandler::$calls);
    }

    public function test_watchers_are_not_dispatched_when_using_save_quietly(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);
        StatusChangedHandler::reset();

        $model->status = 'published';
        $model->saveQuietly();

        $this->assertCount(0, StatusChangedHandler::$calls);
    }
}
