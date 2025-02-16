<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Laragear\MetaModel\CustomMigration;
use Laragear\MetaModel\HasCustomization;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class HasCustomizationTest extends TestCase
{
    protected Container $container;
    protected MockInterface $schema;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();

        TestCustomizableModel::customize(null);
        TestCustomizableModel::$instance = null;
    }

    protected function tearDown(): void
    {
        m::close();
        Container::setInstance();
    }

    public function test_customization(): void
    {
        TestCustomizableModel::customize(function (TestCustomizableModel $model) {
            $model->setTable('test_table');
        });

        static::assertSame('test_table', (new TestCustomizableModel())->getTable());
    }

    public function test_migration_is_not_implemented_by_default(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The '.TestCustomizableModel::class.' has not implemented customizable migrations.');

        TestCustomizableModel::migration();
    }

    public function test_creates_guesses_name_from_caller_and_instances_it(): void
    {
        $migration = TestCustomizableModel::migration();

        $reflection = new ReflectionClass($migration);

        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        static::assertInstanceOf(TestCustomizableModel::class, $property->getValue($migration));
    }

    public function test_creates_uses_same_instance(): void
    {
        TestCustomizableModel::$instance = new TestCustomizableModel();

        $migration = TestCustomizableModel::migrationWithInstance();

        $reflection = new ReflectionClass($migration);

        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        static::assertSame(TestCustomizableModel::$instance, $property->getValue($migration));
    }

    public function test_creates_uses_class_name(): void
    {
        $migration = TestCustomizableModel::migrationWithClassName();

        $reflection = new ReflectionClass($migration);

        $property = $reflection->getProperty('model');
        $property->setAccessible(true);

        static::assertInstanceOf(TestInstancing::class, $property->getValue($migration));
    }
}

class TestCustomizableModel extends Model
{
    use HasCustomization;

    public static $instance;

    public static function migration()
    {
        return CustomMigration::create(fn() => true);
    }

    public static function migrationWithInstance()
    {
        return CustomMigration::create(fn() => true, static::$instance);
    }

    public static function migrationWithClassName()
    {
        return CustomMigration::create(fn() => true, TestInstancing::class);
    }
}

class TestInstancing extends Model
{

}
