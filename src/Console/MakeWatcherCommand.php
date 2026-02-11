<?php

namespace Ascend\LaravelColumnWatcher\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeWatcherCommand extends GeneratorCommand
{
    protected $name = 'make:watcher';

    protected $description = 'Create a new column watcher handler class';

    protected $type = 'Watcher';

    protected function getStub(): string
    {
        $customPath = base_path('stubs/column-watcher/watcher.stub');

        if (file_exists($customPath)) {
            return $customPath;
        }

        return __DIR__.'/../../stubs/watcher.stub';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        if ($this->option('queued')) {
            $stub = str_replace('{{ queueImport }}', "use Illuminate\\Contracts\\Queue\\ShouldQueue;\n", $stub);
            $stub = str_replace('{{ queueInterface }}', ' implements ShouldQueue', $stub);
        } else {
            $stub = str_replace("{{ queueImport }}\n", '', $stub);
            $stub = str_replace('{{ queueInterface }}', '', $stub);
        }

        return $stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return config('column-watcher.namespace', $rootNamespace.'\\Watchers');
    }

    protected function getOptions(): array
    {
        return [
            ['queued', null, InputOption::VALUE_NONE, 'Create a queueable watcher that implements ShouldQueue'],
        ];
    }
}
