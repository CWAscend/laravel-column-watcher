<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Fixtures;

use CWAscend\LaravelColumnWatcher\Attributes\Watch;
use CWAscend\LaravelColumnWatcher\Enums\Timing;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class, timing: Timing::SAVING)]
class TestModelWithSavingTiming extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];
}
