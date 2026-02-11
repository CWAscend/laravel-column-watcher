<?php

namespace Ascend\LaravelColumnWatcher\Tests\Fixtures;

enum TestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Archived = 'archived';
}
