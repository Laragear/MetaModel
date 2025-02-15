<?php

namespace Tests;

use BadMethodCallException;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\TestCustomizableModelWithMigration as TestModel;
use Throwable;
use function is_string;

class CustomMigrationTest extends TestCase
{
    protected Container $container;
    protected MockInterface $schema;
    protected MockInterface $resolver;
    protected MockInterface $connection;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();

        $this->schema = $this->container->instance('db.schema', m::mock(SchemaBuilder::class));
        $this->resolver = $this->container->instance('db', m::mock(ConnectionResolverInterface::class));
        $this->connection = $this->container->instance('db.connection', m::mock(Connection::class));

        $this->resolver->expects('connection')->with(null)->andReturn($this->connection);
        $this->schema->expects('setConnection')->andReturnSelf();

        TestModel::setConnectionResolver($this->resolver);
        TestModel::$create = fn () => true;
        TestModel::customize(null);
    }

    protected function tearDown(): void
    {
        m::close();

        Container::setInstance();
    }

    public function test_creation_callback_receives_blueprint(): void
    {
        TestModel::$create = fn ($blueprint) => Assert::assertInstanceOf(Blueprint::class, $blueprint);

        $this->schema->expects('create')->withArgs(function ($table, $callback) {
            $callback(m::mock(Blueprint::class));

            return true;
        })->andReturnSelf();

        TestModel::migration()->up();
    }

    public function test_creates_table_with_custom_connection(): void
    {
        $this->expectNotToPerformAssertions();

        try {
            m::close();
        } catch (Throwable) {
            // ...
        }

        TestModel::customize(fn ($model) => $model->setConnection('bar'));

        $this->schema = $this->container->instance('db.schema', m::mock(SchemaBuilder::class));
        $this->resolver = $this->container->instance('db', m::mock(ConnectionResolverInterface::class));
        $this->connection = $this->container->instance('db.connection', m::mock(Connection::class));

        $this->resolver->expects('connection')->with(null)->never();
        $this->resolver->expects('connection')->once()->with('bar')->andReturn($this->connection);
        $this->schema->expects('setConnection')->andReturnSelf();

        $this->schema->expects('create')->andReturnSelf();

        TestModel::setConnectionResolver($this->resolver);

        TestModel::migration()->up();
    }

    public function test_creates_table_using_default_model_name(): void
    {
        $this->schema->expects('create')->withArgs(function (string $table): bool {
            static::assertSame('test_customizable_model_with_migrations', $table);
            return true;
        });

        TestModel::migration()->up();
    }

    public function test_creates_table_with_custom_table_name(): void
    {
        TestModel::customize(fn ($model) => $model->setTable('foo'));

        $this->schema->expects('create')->withArgs(function (string $table): bool {
            static::assertSame('foo', $table);
            return true;
        });

        TestModel::migration()->up();
    }

    public function test_creates_columns_bypasses_callback(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();

        $this->schema->expects('create')->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_customizable_model_with_migrations', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->with(fn($table) => $table->createCall())->up();
    }

    public function test_morphs_throws_if_called_twice(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('morphs')->with('foo', null)->once();

        $exception = null;

        $this->schema->expects('create')->once()->withArgs(
            function (string $table, Closure $closure) use ($blueprint, &$exception): bool {
                try {
                    $closure($blueprint);
                } catch (Throwable $e) {
                    $exception = $e;
                }

                return true;
            }
        );

        TestModel::$create = function ($table) {
            $this->createMorph($table, 'foo');
            $this->createMorph($table, 'foo');
        };

        TestModel::migration()->up();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Using multiple customizable morph calls is unsupported.');

        throw $exception;
    }

    public function test_morph_nullable_throws_if_called_twice(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('nullableMorphs')->with('foo', null)->once();

        $exception = null;

        $this->schema->expects('create')->once()->withArgs(
            function (string $table, Closure $closure) use ($blueprint, &$exception): bool {
                try {
                    $closure($blueprint);
                } catch (Throwable $e) {
                    $exception = $e;
                }

                return true;
            }
        );

        TestModel::$create = function (Blueprint $table): void {
            $this->createNullableMorph($table, 'foo');
            $this->createNullableMorph($table, 'foo');
        };

        TestModel::migration()->up();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Using multiple customizable morph calls is unsupported.');

        throw $exception;
    }

    public static function useMigrations(): array
    {
        return [
            ['migration' => function (Blueprint $table) {
                $table->createCall();

                $this->createMorph($table, 'foo');
                $this->morphCalled = false;
                $this->createNullableMorph($table, 'bar');
            }, 'index' => null],
            ['migration' => function (Blueprint $table) {
                $table->createCall();

                $this->createMorph($table, 'foo', 'custom_index');
                $this->morphCalled = false;
                $this->createNullableMorph($table, 'bar', 'custom_index');
            }, 'index' => 'custom_index'],
        ];
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_default_from_builder(Closure $migration, ?string $index): void
    {
        TestModel::$create = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();
        $blueprint->expects('morphs')->with('foo', $index)->once();
        $blueprint->expects('nullableMorphs')->with('bar', $index)->once();

        $this->schema->expects('create')->once()
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                static::assertSame('test_customizable_model_with_migrations', $table);
                $closure($blueprint);

                return true;
            });

        TestModel::migration()->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_numeric(Closure $migration, ?string $index): void
    {
        TestModel::$create = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('numericMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableNumericMorphs')->with('bar', $index)->twice();
        $blueprint->expects('numericMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableNumericMorphs')->with('bar', 'test_index')->once();

        $this->resolver->expects('connection')->with(null)->times(2)->andReturn($this->connection);
        $this->schema->expects('setConnection')->times(2)->andReturnSelf();

        $this->schema->expects('create')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                static::assertSame('test_customizable_model_with_migrations', $table);

                $closure($blueprint);

                return true;
            });

        TestModel::migration()->morphNumeric->up();
        TestModel::migration()->morph('numeric')->up();
        TestModel::migration()->morph('numeric', 'test_index')->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_uuid(Closure $migration, ?string $index): void
    {
        TestModel::$create = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('uuidMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableUuidMorphs')->with('bar', $index)->twice();
        $blueprint->expects('uuidMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableUuidMorphs')->with('bar', 'test_index')->once();

        $this->resolver->expects('connection')->with(null)->times(2)->andReturn($this->connection);
        $this->schema->expects('setConnection')->times(2)->andReturnSelf();

        $this->schema->expects('create')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                static::assertSame('test_customizable_model_with_migrations', $table);
                $closure($blueprint);

                return true;
            });

        TestModel::migration()->morphUuid->up();
        TestModel::migration()->morph('uuid')->up();
        TestModel::migration()->morph('uuid', 'test_index')->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_ulid(Closure $migration, ?string $index): void
    {
        TestModel::$create = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('ulidMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableUlidMorphs')->with('bar', $index)->twice();
        $blueprint->expects('ulidMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableUlidMorphs')->with('bar', 'test_index')->once();

        $this->resolver->expects('connection')->with(null)->times(2)->andReturn($this->connection);
        $this->schema->expects('setConnection')->times(2)->andReturnSelf();

        $this->schema->expects('create')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                static::assertSame('test_customizable_model_with_migrations', $table);
                $closure($blueprint);

                return true;
            });

        TestModel::migration()->morphUlid->up();
        TestModel::migration()->morph('ulid')->up();
        TestModel::migration()->morph('ulid', 'test_index')->up();
    }

    public function test_calls_after_up(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();

        $this->schema->expects('create')->once();
        $this->schema->expects('table')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_customizable_model_with_migrations', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()
            ->afterUp(fn($table) => $table->firstCall())
            ->afterUp(fn($table) => $table->secondCall(), fn($table) => $table->thirdCall())
            ->up();
    }

    public function test_drops_table_automatically(): void
    {
        $this->expectNotToPerformAssertions();

        $this->schema->expects('dropIfExists')->with('test_customizable_model_with_migrations')->once();

        TestModel::migration()->down();
    }

    public function test_calls_before_down(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();

        $this->schema->expects('table')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                static::assertSame('test_customizable_model_with_migrations', $table);
                $closure($blueprint);

                return true;
            });

        $this->schema->expects('dropIfExists')->with('test_customizable_model_with_migrations')->once();

        TestModel::migration()
            ->beforeDown(fn($table) => $table->firstCall())
            ->beforeDown(fn($table) => $table->secondCall(), fn($table) => $table->thirdCall())
            ->down();
    }
}
