<?php

namespace Ascend\LaravelColumnWatcher\Attributes;

use Ascend\LaravelColumnWatcher\Enums\Timing;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Watch
{
    public array $columns;

    /**
     * @param  ?bool  $onCreate  Fire when the model is first inserted (old value is null). Null keeps the
     *                           timing's default: SAVING fires on create, SAVED does not.
     * @param  ?bool  $onUpdate  Fire when an existing row is updated. Null keeps the default (true).
     */
    public function __construct(
        string|array $columns,
        public string $handler,
        public Timing $timing = Timing::SAVED,
        public ?bool $onCreate = null,
        public ?bool $onUpdate = null,
    ) {
        $this->columns = is_array($columns) ? $columns : [$columns];
    }
}
