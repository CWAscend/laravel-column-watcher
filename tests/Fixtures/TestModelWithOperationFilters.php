<?php

namespace Ascend\LaravelColumnWatcher\Tests\Fixtures;

use Ascend\LaravelColumnWatcher\Attributes\Watch;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class, onCreate: true)]
#[Watch('priority', MultiColumnHandler::class, onCreate: true, onUpdate: false)]
#[Watch('name', NameChangedHandler::class, timing: Timing::SAVING, onCreate: false)]
class TestModelWithOperationFilters extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];
}
