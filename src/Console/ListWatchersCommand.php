<?php

namespace CWAscend\LaravelColumnWatcher\Console;

use CWAscend\LaravelColumnWatcher\Support\WatcherRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ListWatchersCommand extends Command
{
    protected $signature = 'watcher:list';

    protected $description = 'List all registered column watchers';

    public function handle(WatcherRegistry $registry): int
    {
        $this->scanModelsForWatchers($registry);

        $watchers = $registry->all();

        if (empty($watchers)) {
            $this->components->info('No column watchers are registered.');

            return self::SUCCESS;
        }

        // Group by model.column.timing for display
        $grouped = $this->groupWatchers($watchers);

        $this->displayWatchers($grouped);

        return self::SUCCESS;
    }

    /**
     * Scan configured model directories for Watch attributes.
     */
    protected function scanModelsForWatchers(WatcherRegistry $registry): void
    {
        $modelPaths = config('column-watcher.model_paths', ['app/Models']);

        foreach ($modelPaths as $path) {
            $fullPath = base_path($path);

            if (! is_dir($fullPath)) {
                continue;
            }

            $modelClasses = $this->discoverModels($fullPath, $path);
            $registry->scanModels($modelClasses);
        }
    }

    /**
     * Discover Eloquent model classes in a directory.
     *
     * @return array<string>
     */
    protected function discoverModels(string $fullPath, string $relativePath): array
    {
        $models = [];

        $finder = new Finder;
        $finder->files()->in($fullPath)->name('*.php');

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath(), $relativePath);

            if ($className && $this->isEloquentModel($className)) {
                $models[] = $className;
            }
        }

        return $models;
    }

    /**
     * Convert a file path to a fully qualified class name.
     */
    protected function getClassNameFromFile(string $filePath, string $relativePath): ?string
    {
        $content = File::get($filePath);

        // Extract namespace
        if (! preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        // Extract class name
        if (! preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1].'\\'.$classMatch[1];
    }

    /**
     * Check if a class is an Eloquent model.
     */
    protected function isEloquentModel(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);

            return $reflection->isSubclassOf(Model::class)
                && ! $reflection->isAbstract();
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Group watchers by model.column.timing key.
     *
     * @param  array<int, array{model: string, column: string, timing: \CWAscend\LaravelColumnWatcher\Enums\Timing, handler: string}>  $watchers
     * @return array<string, array{model: string, column: string, timing: \CWAscend\LaravelColumnWatcher\Enums\Timing, handlers: array<string>}>
     */
    protected function groupWatchers(array $watchers): array
    {
        $grouped = [];

        foreach ($watchers as $watcher) {
            $key = $watcher['model'].'.'.$watcher['column'].'.'.$watcher['timing']->name;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'model' => $watcher['model'],
                    'column' => $watcher['column'],
                    'timing' => $watcher['timing'],
                    'handlers' => [],
                ];
            }

            $grouped[$key]['handlers'][] = $watcher['handler'];
        }

        // Sort by key for consistent output
        ksort($grouped);

        return $grouped;
    }

    /**
     * Display watchers in event:list style format.
     */
    protected function displayWatchers(array $grouped): void
    {
        $terminalWidth = $this->getTerminalWidth();

        foreach ($grouped as $group) {
            $header = $group['model'].'.'.$group['column'].' ('.$group['timing']->name.')';
            $dots = str_repeat('.', max(1, $terminalWidth - strlen($header) - 1));

            $this->line("  <fg=blue>{$header}</> <fg=gray>{$dots}</>");

            foreach ($group['handlers'] as $handler) {
                $queueIndicator = $this->isQueueable($handler) ? ' <fg=yellow>[queued]</>' : '';
                $this->line("  <fg=gray>⇂</> {$handler}{$queueIndicator}");
            }
        }
    }

    /**
     * Check if a handler is queueable.
     */
    protected function isQueueable(string $handlerClass): bool
    {
        return is_subclass_of($handlerClass, ShouldQueue::class);
    }

    /**
     * Get the terminal width.
     */
    protected function getTerminalWidth(): int
    {
        if (method_exists($this->output, 'getTerminalWidth')) {
            return $this->output->getTerminalWidth();
        }

        return 120;
    }
}
