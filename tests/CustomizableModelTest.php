<?php

namespace Tests;

use Tests\Fixtures\TestModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CustomizableModelTest extends TestCase
{
    protected function setUp(): void
    {
        TestModel::$useTable = null;
        TestModel::$useCasts = [];
        TestModel::$useFillable = [];
        TestModel::$useGuarded = [];
        TestModel::$useHidden = [];
        TestModel::$useVisible = [];
    }

    #[Test]
    public function uses_default_table_name(): void
    {
        TestModel::$useTable = 'foo';

        static::assertSame('foo', (new TestModel())->getTable());
    }

    #[Test]
    public function uses_custom_table_name(): void
    {
        static::assertSame('test_models', (new TestModel())->getTable());
    }

    #[Test]
    public function merges_casts(): void
    {
        TestModel::$useCasts = ['foo' => 'bar'];

        static::assertSame(['id' => 'int', 'foo' => 'bar'], (new TestModel())->getCasts());
    }

    #[Test]
    public function merges_casts_callback(): void
    {
        TestModel::$useCasts = fn(TestModel $model) => ['foo' => 'bar'];

        static::assertSame(['id' => 'int', 'foo' => 'bar'], (new TestModel())->getCasts());
    }

    #[Test]
    public function merges_fillable(): void
    {
        TestModel::$useFillable = ['foo', 'bar'];

        static::assertSame(['foo', 'bar'], (new TestModel())->getFillable());
    }

    #[Test]
    public function merges_fillable_callback(): void
    {
        TestModel::$useFillable = fn(TestModel $model) => ['foo', 'bar'];

        static::assertSame(['foo', 'bar'], (new TestModel())->getFillable());
    }

    #[Test]
    public function merges_guarded(): void
    {
        TestModel::$useGuarded = ['foo', 'bar'];

        static::assertSame(['foo', 'bar'], (new TestModel())->getGuarded());
    }

    #[Test]
    public function merges_guarded_callback(): void
    {
        TestModel::$useGuarded = fn(TestModel $model) => ['foo', 'bar'];

        static::assertSame(['foo', 'bar'], (new TestModel())->getGuarded());
    }

    #[Test]
    public function merges_hidden(): void
    {
        TestModel::$useHidden = ['foo', 'bar'];

        static::assertSame(['baz', 'foo', 'bar'], (new TestModel())->getHidden());
    }

    #[Test]
    public function merges_hidden_callback(): void
    {
        TestModel::$useHidden = fn(TestModel $model) => ['foo', 'bar'];

        static::assertSame(['baz', 'foo', 'bar'], (new TestModel())->getHidden());
    }

    #[Test]
    public function merge_visible(): void
    {
        TestModel::$useVisible = ['foo', 'bar'];

        static::assertSame(['quz', 'foo', 'bar'], (new TestModel())->getVisible());
    }

    #[Test]
    public function merge_visible_callback(): void
    {
        TestModel::$useVisible = fn(TestModel $model) => ['foo', 'bar'];

        static::assertSame(['quz', 'foo', 'bar'], (new TestModel())->getVisible());
    }

    #[Test]
    public function merge_appends(): void
    {
        TestModel::$useAppends = ['foo', 'bar'];

        static::assertSame(['qux', 'foo', 'bar'], (new TestModel())->getAppends());
    }

    #[Test]
    public function merge_appends_callback(): void
    {
        TestModel::$useAppends = fn(TestModel $model) => ['foo', 'bar'];

        static::assertSame(['qux', 'foo', 'bar'], (new TestModel())->getAppends());
    }
}


