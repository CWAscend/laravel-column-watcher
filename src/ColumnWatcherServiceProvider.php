<?php

namespace Ascend\LaravelColumnWatcher;

use Ascend\LaravelColumnWatcher\Console\ListWatchersCommand;
use Ascend\LaravelColumnWatcher\Console\MakeWatcherCommand;
use Ascend\LaravelColumnWatcher\Support\ColumnWatcherState;
use Ascend\LaravelColumnWatcher\Support\ColumnWatcherSubscriber;
use Ascend\LaravelColumnWatcher\Support\WatcherRegistry;
use Illuminate\Support\ServiceProvider;

class ColumnWatcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/column-watcher.php', 'column-watcher');

        $this->app->singleton(WatcherRegistry::class);

        // Scoped binding ensures state is reset between requests (Octane compatible)
        $this->app->scoped(ColumnWatcherState::class);
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishStubs();
        $this->registerCommands();
        $this->registerEventSubscriber();
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/column-watcher.php' => config_path('column-watcher.php'),
        ], 'column-watcher-config');
    }

    protected function publishStubs(): void
    {
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/column-watcher'),
        ], 'column-watcher-stubs');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListWatchersCommand::class,
                MakeWatcherCommand::class,
            ]);
        }
    }

    protected function registerEventSubscriber(): void
    {
        $this->app->make('events')->subscribe(
            $this->app->make(ColumnWatcherSubscriber::class)
        );
    }
}
