<?php

namespace Ascend\LaravelColumnWatcher\Tests\Fixtures;

use Ascend\LaravelColumnWatcher\Attributes\Watch;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class, Timing::CREATED)]
#[Watch('status', StatusChangedHandler::class, Timing::UPDATED)]
#[Watch('priority', MultiColumnHandler::class, Timing::CREATED)]
#[Watch('name', NameChangedHandler::class, Timing::UPDATING)]
class TestModelWithCreateUpdateTimings extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];
}
