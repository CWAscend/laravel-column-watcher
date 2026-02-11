<?php

namespace Ascend\LaravelColumnWatcher\Tests\Fixtures;

use Ascend\LaravelColumnWatcher\Attributes\Watch;
use Ascend\LaravelColumnWatcher\Enums\Timing;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class, timing: Timing::SAVING)]
class TestModelWithSavingTiming extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];
}
