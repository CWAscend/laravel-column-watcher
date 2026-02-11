<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Feature;

use CWAscend\LaravelColumnWatcher\ColumnWatcher;
use CWAscend\LaravelColumnWatcher\Enums\Timing;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\DirectQueueableHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\MultiColumnHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ListWatchersCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWatchers();
    }

    public function test_shows_no_watchers_message_when_empty(): void
    {
        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();
    }

    public function test_lists_registered_watchers(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('TestModel.status (SAVED)')
            ->expectsOutputToContain(StatusChangedHandler::class)
            ->assertSuccessful();
    }

    public function test_shows_timing_for_saving_watchers(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class, Timing::SAVING);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('TestModel.status (SAVING)')
            ->assertSuccessful();
    }

    public function test_shows_queued_indicator_for_queueable_handlers(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('[queued]')
            ->assertSuccessful();
    }

    public function test_lists_multiple_handlers_for_same_column(): void
    {
        ColumnWatcher::register(TestModel::class, 'status', StatusChangedHandler::class);
        ColumnWatcher::register(TestModel::class, 'status', DirectQueueableHandler::class);

        $this->artisan('watcher:list')
            ->expectsOutputToContain(StatusChangedHandler::class)
            ->expectsOutputToContain(DirectQueueableHandler::class)
            ->assertSuccessful();
    }

    public function test_skips_non_existent_model_paths(): void
    {
        config(['column-watcher.model_paths' => ['non/existent/path']]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();
    }

    public function test_discovers_watchers_from_model_attributes(): void
    {
        $relativePath = 'watcher-test-models-'.uniqid();
        $tempDir = base_path($relativePath);
        mkdir($tempDir, 0755, true);

        $modelContent = <<<'PHP'
<?php

namespace App\Models;

use CWAscend\LaravelColumnWatcher\Attributes\Watch;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class)]
class DiscoverableModel extends Model
{
    protected $table = 'test_models';
}
PHP;

        File::put($tempDir.'/DiscoverableModel.php', $modelContent);

        // Register autoloader for the temp namespace
        spl_autoload_register(function ($class) use ($tempDir) {
            if ($class === 'App\\Models\\DiscoverableModel') {
                require_once $tempDir.'/DiscoverableModel.php';
            }
        });

        config(['column-watcher.model_paths' => [$relativePath]]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('DiscoverableModel.status (SAVED)')
            ->expectsOutputToContain(StatusChangedHandler::class)
            ->assertSuccessful();

        // Cleanup
        File::deleteDirectory($tempDir);
    }

    public function test_skips_files_without_namespace(): void
    {
        $relativePath = 'watcher-test-models-'.uniqid();
        $tempDir = base_path($relativePath);
        mkdir($tempDir, 0755, true);

        $noNamespaceContent = <<<'PHP'
<?php

class NoNamespaceModel
{
    // No namespace declaration
}
PHP;

        File::put($tempDir.'/NoNamespaceModel.php', $noNamespaceContent);

        config(['column-watcher.model_paths' => [$relativePath]]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();

        File::deleteDirectory($tempDir);
    }

    public function test_skips_files_without_class_definition(): void
    {
        $relativePath = 'watcher-test-models-'.uniqid();
        $tempDir = base_path($relativePath);
        mkdir($tempDir, 0755, true);

        $noClassContent = <<<'PHP'
<?php

namespace App\Models;

// Just some functions, no class
function helperFunction() {
    return true;
}
PHP;

        File::put($tempDir.'/helpers.php', $noClassContent);

        config(['column-watcher.model_paths' => [$relativePath]]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();

        File::deleteDirectory($tempDir);
    }

    public function test_skips_non_existent_classes(): void
    {
        $relativePath = 'watcher-test-models-'.uniqid();
        $tempDir = base_path($relativePath);
        mkdir($tempDir, 0755, true);

        // Create a file that declares a class but it won't be autoloaded
        $nonLoadableContent = <<<'PHP'
<?php

namespace App\Models\NonLoadable;

use Illuminate\Database\Eloquent\Model;

class NonLoadableModel extends Model
{
}
PHP;

        File::put($tempDir.'/NonLoadableModel.php', $nonLoadableContent);

        config(['column-watcher.model_paths' => [$relativePath]]);

        // The class won't exist because it's not autoloaded
        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();

        File::deleteDirectory($tempDir);
    }

    public function test_skips_non_model_classes(): void
    {
        $relativePath = 'watcher-test-models-'.uniqid();
        $tempDir = base_path($relativePath);
        mkdir($tempDir, 0755, true);

        $nonModelContent = <<<'PHP'
<?php

namespace App\Services;

class NotAModel
{
    public function doSomething(): void
    {
    }
}
PHP;

        File::put($tempDir.'/NotAModel.php', $nonModelContent);

        // Register autoloader
        spl_autoload_register(function ($class) use ($tempDir) {
            if ($class === 'App\\Services\\NotAModel') {
                require_once $tempDir.'/NotAModel.php';
            }
        });

        config(['column-watcher.model_paths' => [$relativePath]]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();

        File::deleteDirectory($tempDir);
    }

    public function test_skips_abstract_model_classes(): void
    {
        $relativePath = 'watcher-test-models-'.uniqid();
        $tempDir = base_path($relativePath);
        mkdir($tempDir, 0755, true);

        $abstractModelContent = <<<'PHP'
<?php

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractBaseModel extends Model
{
    protected $guarded = [];
}
PHP;

        File::put($tempDir.'/AbstractBaseModel.php', $abstractModelContent);

        // Register autoloader
        spl_autoload_register(function ($class) use ($tempDir) {
            if ($class === 'App\\Models\\Base\\AbstractBaseModel') {
                require_once $tempDir.'/AbstractBaseModel.php';
            }
        });

        config(['column-watcher.model_paths' => [$relativePath]]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('No column watchers are registered.')
            ->assertSuccessful();

        File::deleteDirectory($tempDir);
    }

    public function test_handles_multiple_model_paths(): void
    {
        $relativePath1 = 'watcher-test-models1-'.uniqid();
        $relativePath2 = 'watcher-test-models2-'.uniqid();
        $tempDir1 = base_path($relativePath1);
        $tempDir2 = base_path($relativePath2);
        mkdir($tempDir1, 0755, true);
        mkdir($tempDir2, 0755, true);

        $model1Content = <<<'PHP'
<?php

namespace App\Models\Domain1;

use CWAscend\LaravelColumnWatcher\Attributes\Watch;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\StatusChangedHandler;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class)]
class Model1 extends Model
{
    protected $table = 'test_models';
}
PHP;

        $model2Content = <<<'PHP'
<?php

namespace App\Models\Domain2;

use CWAscend\LaravelColumnWatcher\Attributes\Watch;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\MultiColumnHandler;
use Illuminate\Database\Eloquent\Model;

#[Watch('name', MultiColumnHandler::class)]
class Model2 extends Model
{
    protected $table = 'test_models';
}
PHP;

        File::put($tempDir1.'/Model1.php', $model1Content);
        File::put($tempDir2.'/Model2.php', $model2Content);

        // Register autoloaders
        spl_autoload_register(function ($class) use ($tempDir1, $tempDir2) {
            if ($class === 'App\\Models\\Domain1\\Model1') {
                require_once $tempDir1.'/Model1.php';
            }
            if ($class === 'App\\Models\\Domain2\\Model2') {
                require_once $tempDir2.'/Model2.php';
            }
        });

        config(['column-watcher.model_paths' => [$relativePath1, $relativePath2]]);

        $this->artisan('watcher:list')
            ->expectsOutputToContain('Model1.status (SAVED)')
            ->expectsOutputToContain('Model2.name (SAVED)')
            ->assertSuccessful();

        File::deleteDirectory($tempDir1);
        File::deleteDirectory($tempDir2);
    }
}
