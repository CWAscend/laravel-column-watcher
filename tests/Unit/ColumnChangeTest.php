<?php

namespace CWAscend\LaravelColumnWatcher\Tests\Unit;

use CWAscend\LaravelColumnWatcher\Data\ColumnChange;
use CWAscend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use CWAscend\LaravelColumnWatcher\Tests\TestCase;

class ColumnChangeTest extends TestCase
{
    public function test_stores_all_properties(): void
    {
        $model = new TestModel(['name' => 'Test']);

        $change = new ColumnChange(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published'
        );

        $this->assertSame($model, $change->model);
        $this->assertEquals('status', $change->column);
        $this->assertEquals('draft', $change->oldValue);
        $this->assertEquals('published', $change->newValue);
    }

    public function test_has_changed_returns_true_when_values_differ(): void
    {
        $change = new ColumnChange(
            model: new TestModel,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published'
        );

        $this->assertTrue($change->hasChanged());
    }

    public function test_has_changed_returns_false_when_values_same(): void
    {
        $change = new ColumnChange(
            model: new TestModel,
            column: 'status',
            oldValue: 'draft',
            newValue: 'draft'
        );

        $this->assertFalse($change->hasChanged());
    }

    public function test_was_null_returns_true_when_old_value_null(): void
    {
        $change = new ColumnChange(
            model: new TestModel,
            column: 'name',
            oldValue: null,
            newValue: 'Test'
        );

        $this->assertTrue($change->wasNull());
        $this->assertFalse($change->isNull());
    }

    public function test_is_null_returns_true_when_new_value_null(): void
    {
        $change = new ColumnChange(
            model: new TestModel,
            column: 'name',
            oldValue: 'Test',
            newValue: null
        );

        $this->assertFalse($change->wasNull());
        $this->assertTrue($change->isNull());
    }

    public function test_was_empty_returns_true_for_empty_old_value(): void
    {
        $change = new ColumnChange(
            model: new TestModel,
            column: 'name',
            oldValue: '',
            newValue: 'Test'
        );

        $this->assertTrue($change->wasEmpty());
        $this->assertFalse($change->isEmpty());
    }

    public function test_is_empty_returns_true_for_empty_new_value(): void
    {
        $change = new ColumnChange(
            model: new TestModel,
            column: 'name',
            oldValue: 'Test',
            newValue: ''
        );

        $this->assertFalse($change->wasEmpty());
        $this->assertTrue($change->isEmpty());
    }
}
