<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use InvalidArgumentException;
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
        Container::setInstance();

        m::close();
    }

    public function test_customization_with_string_as_table_name(): void
    {
        TestCustomizableModel::customize('test_table');

        static::assertSame('test_table', (new TestCustomizableModel())->getTable());
    }

    public function test_customization_with_callback(): void
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

    public function test_migrations_receives_with_as_closure(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('string');

        $schema = m::mock(Builder::class);
        $schema->expects('create')->andReturnUsing(function ($table, $callback) use ($blueprint) {
            $callback($blueprint);
        });

        $container = Container::setInstance(m::mock(Container::class));
        $container->expects('make')->withArgs(function (string $class) {
            return $class === Builder::class;
        })->andReturn($schema);

        TestCustomizableModelWithManyMigrations::migration(static function ($blueprint) {
            static::assertInstanceOf(Blueprint::class, $blueprint);

            $blueprint->string('test_column');
        })->up();
    }

    public function test_migration_finds_by_key(): void
    {
        $default = TestCustomizableModelWithManyMigrations::migration();

        static::assertSame('test_customizable_model_with_many_migrations', $default->table);

        $custom = TestCustomizableModelWithManyMigrations::migration('custom_migration');

        static::assertSame('custom_migration', $custom->table);
    }

    public function test_throws_when_migration_key_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The migration [invalid_migration] does not exist.');

        TestCustomizableModelWithManyMigrations::migration('invalid_migration');
    }

    public function test_guesses_table_from_model_by_backtrace(): void
    {
        $migration = TestCustomizableModel::migrationGuess();

        static::assertSame('test_customizable_models', $migration->table);
    }

    public function test_guesses_table_from_model_instance(): void
    {
        TestCustomizableModel::$instance = new TestCustomizableModel();

        $migration = TestCustomizableModel::migrationWithInstance();

        static::assertSame('test_customizable_models', $migration->table);
    }

    public function test_guesses_no_table_when_set_manually(): void
    {
        $migration = TestCustomizableModel::migrationWithTableName();

        static::assertSame('test_table', $migration->table);
    }

    public function test_uses_connection_name_when_set_manually(): void
    {
        $migration = TestCustomizableModel::migrationWithConnectionName();

        static::assertSame('test_connection', $migration->getConnection());
    }

    public function test_uses_table_and_connection_when_set_manually(): void
    {
        $migration = TestCustomizableModel::migrationWithTableNameAndConnectionName();

        static::assertSame('test_connection', $migration->getConnection());
        static::assertSame('test_table', $migration->table);
    }
}

class TestCustomizableModel extends Model
{
    use HasCustomization;

    public static $instance;

    public static function migrationGuess()
    {
        return CustomMigration::make(fn() => true);
    }

    public static function migrationWithInstance()
    {
        return CustomMigration::make(fn() => true, static::$instance);
    }

    public static function migrationWithTableName()
    {
        return CustomMigration::make(fn() => true, model: 'test_table');
    }

    public static function migrationWithConnectionName()
    {
        return CustomMigration::make(fn() => true, connection: 'test_connection');
    }

    public static function migrationWithTableNameAndConnectionName()
    {
        return CustomMigration::make(fn() => true, model: 'test_table', connection: 'test_connection');
    }
}

class TestCustomizableModelWithManyMigrations extends Model
{
    use HasCustomization;

    protected static function makeMigration(): CustomMigration|array
    {
        return [
            CustomMigration::make(fn() => true),
            CustomMigration::make(fn() => true, 'custom_migration'),
        ];
    }
}



class TestInstancing extends Model
{

}
