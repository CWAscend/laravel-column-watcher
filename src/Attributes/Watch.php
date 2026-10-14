<?php

namespace Ascend\LaravelColumnWatcher\Attributes;

use Ascend\LaravelColumnWatcher\Enums\Timing;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Watch
{
    public array $columns;

    public function __construct(
        string|array $columns,
        public string $handler,
        public Timing $timing = Timing::SAVED,
    ) {
        $this->columns = is_array($columns) ? $columns : [$columns];
    }
}
