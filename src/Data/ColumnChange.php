<?php

namespace Ascend\LaravelColumnWatcher\Data;

use Illuminate\Database\Eloquent\Model;

readonly class ColumnChange
{
    public function __construct(
        public Model $model,
        public string $column,
        public mixed $oldValue,
        public mixed $newValue,
    ) {}

    public function hasChanged(): bool
    {
        return $this->oldValue !== $this->newValue;
    }

    public function wasNull(): bool
    {
        return $this->oldValue === null;
    }

    public function isNull(): bool
    {
        return $this->newValue === null;
    }

    public function wasEmpty(): bool
    {
        return empty($this->oldValue);
    }

    public function isEmpty(): bool
    {
        return empty($this->newValue);
    }
}
