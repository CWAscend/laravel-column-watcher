<?php

namespace Ascend\LaravelColumnWatcher\Tests\Feature;

use Ascend\LaravelColumnWatcher\ColumnWatcher;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\FakeableHandler;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestModel;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestPriority;
use Ascend\LaravelColumnWatcher\Tests\Fixtures\TestStatus;
use Ascend\LaravelColumnWatcher\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class EnumValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWatchers();
        FakeableHandler::reset();

        ColumnWatcher::register(TestModel::class, 'status', FakeableHandler::class);
    }

    protected function tearDown(): void
    {
        FakeableHandler::reset();

        parent::tearDown();
    }

    public function test_assert_triggered_with_values_works_with_backed_enum(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        // Simulate a watcher being triggered with enum values
        $job = new FakeableHandler(
            model: $model,
            column: 'status',
            oldValue: TestStatus::Draft,
            newValue: TestStatus::Published,
        );

        $job->handle();

        // Should pass without throwing string interpolation error
        FakeableHandler::assertTriggeredWithValues(TestStatus::Draft, TestStatus::Published);
    }

    public function test_assert_triggered_with_values_works_with_unit_enum(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'priority',
            oldValue: TestPriority::Normal,
            newValue: TestPriority::High,
        );

        $job->handle();

        FakeableHandler::assertTriggeredWithValues(TestPriority::Normal, TestPriority::High);
    }

    public function test_assert_triggered_with_values_error_message_includes_enum_value(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'status',
            oldValue: TestStatus::Draft,
            newValue: TestStatus::Pending,
        );

        $job->handle();

        try {
            // Try to assert with wrong values - should fail with readable message
            FakeableHandler::assertTriggeredWithValues(TestStatus::Draft, TestStatus::Published);
            $this->fail('Expected AssertionFailedError was not thrown');
        } catch (AssertionFailedError $e) {
            // The error message should contain the string representation of the enum values
            $this->assertStringContainsString('draft', $e->getMessage());
            $this->assertStringContainsString('published', $e->getMessage());
        }
    }

    public function test_assert_triggered_with_values_error_message_includes_unit_enum_name(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'priority',
            oldValue: TestPriority::Low,
            newValue: TestPriority::Normal,
        );

        $job->handle();

        try {
            FakeableHandler::assertTriggeredWithValues(TestPriority::Low, TestPriority::High);
            $this->fail('Expected AssertionFailedError was not thrown');
        } catch (AssertionFailedError $e) {
            // Unit enums should show their name
            $this->assertStringContainsString('Low', $e->getMessage());
            $this->assertStringContainsString('High', $e->getMessage());
        }
    }

    public function test_assert_triggered_with_values_handles_null_values(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'status',
            oldValue: null,
            newValue: 'published',
        );

        $job->handle();

        FakeableHandler::assertTriggeredWithValues(null, 'published');
    }

    public function test_assert_triggered_with_values_error_message_shows_null(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: 'published',
        );

        $job->handle();

        try {
            FakeableHandler::assertTriggeredWithValues(null, 'published');
            $this->fail('Expected AssertionFailedError was not thrown');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('null', $e->getMessage());
        }
    }

    public function test_assert_triggered_with_values_handles_boolean_values(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'is_active',
            oldValue: false,
            newValue: true,
        );

        $job->handle();

        FakeableHandler::assertTriggeredWithValues(false, true);
    }

    public function test_assert_triggered_with_values_error_message_shows_booleans(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'is_active',
            oldValue: true,
            newValue: false,
        );

        $job->handle();

        try {
            FakeableHandler::assertTriggeredWithValues(false, true);
            $this->fail('Expected AssertionFailedError was not thrown');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('false', $e->getMessage());
            $this->assertStringContainsString('true', $e->getMessage());
        }
    }

    public function test_assert_triggered_with_values_handles_array_values(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'settings',
            oldValue: ['key' => 'old'],
            newValue: ['key' => 'new'],
        );

        $job->handle();

        FakeableHandler::assertTriggeredWithValues(['key' => 'old'], ['key' => 'new']);
    }

    public function test_assert_triggered_with_mixed_enum_and_string(): void
    {
        FakeableHandler::fake();

        $model = TestModel::create(['name' => 'Test', 'status' => 'draft']);

        $job = new FakeableHandler(
            model: $model,
            column: 'status',
            oldValue: 'draft',
            newValue: TestStatus::Published,
        );

        $job->handle();

        FakeableHandler::assertTriggeredWithValues('draft', TestStatus::Published);
    }
}
