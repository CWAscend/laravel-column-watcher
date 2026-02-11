<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeWatcherCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up generated files
        $path = app_path('Watchers');
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }

        parent::tearDown();
    }

    public function test_can_generate_watcher_class(): void
    {
        $this->artisan('make:watcher', ['name' => 'TestWatcher'])
            ->assertSuccessful();

        $this->assertFileExists(app_path('Watchers/TestWatcher.php'));
    }

    public function test_generated_watcher_extends_base_watcher(): void
    {
        $this->artisan('make:watcher', ['name' => 'TestWatcher'])
            ->assertSuccessful();

        $contents = File::get(app_path('Watchers/TestWatcher.php'));

        $this->assertStringContainsString('extends ColumnWatcher', $contents);
        $this->assertStringContainsString('use Ascend\LaravelColumnWatcher\ColumnWatcher', $contents);
        $this->assertStringContainsString('use Ascend\LaravelColumnWatcher\Data\ColumnChange', $contents);
        $this->assertStringContainsString('protected function execute(ColumnChange $change): void', $contents);
    }

    public function test_generated_queueable_watcher_implements_should_queue(): void
    {
        $this->artisan('make:watcher', ['name' => 'QueueableTestWatcher', '--queued' => true])
            ->assertSuccessful();

        $contents = File::get(app_path('Watchers/QueueableTestWatcher.php'));

        $this->assertStringContainsString('extends ColumnWatcher', $contents);
        $this->assertStringContainsString('implements ShouldQueue', $contents);
        $this->assertStringContainsString('use Illuminate\Contracts\Queue\ShouldQueue', $contents);
    }

    public function test_can_generate_nested_watcher(): void
    {
        $this->artisan('make:watcher', ['name' => 'Orders/StatusChangedHandler'])
            ->assertSuccessful();

        $this->assertFileExists(app_path('Watchers/Orders/StatusChangedHandler.php'));
    }
}
