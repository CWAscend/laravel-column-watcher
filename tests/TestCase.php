<?php

namespace CWAscend\LaravelColumnWatcher\Tests;

use CWAscend\LaravelColumnWatcher\ColumnWatcherServiceProvider;
use CWAscend\LaravelColumnWatcher\Support\ColumnWatcherState;
use CWAscend\LaravelColumnWatcher\Support\WatcherRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ColumnWatcherServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('column-watcher.enabled', true);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->timestamps();
        });
    }

    protected function clearWatchers(): void
    {
        $this->app->make(WatcherRegistry::class)->clear();
    }

    protected function resetState(): void
    {
        $this->app->make(ColumnWatcherState::class)->reset();
    }
}
