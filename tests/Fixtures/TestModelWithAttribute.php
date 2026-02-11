<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Fixtures;

use CWAscend\LaravelColumnWatcher\Attributes\Watch;
use Illuminate\Database\Eloquent\Model;

#[Watch('status', StatusChangedHandler::class)]
#[Watch(['name', 'priority'], MultiColumnHandler::class)]
class TestModelWithAttribute extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];
}
