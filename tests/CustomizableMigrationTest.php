<?php

namespace Tests;

use BadMethodCallException;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Laragear\MetaModel\CustomizableMigration;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\TestMigration;
use Tests\Fixtures\TestMigrationWithMorph;
use Tests\Fixtures\TestMigrationWithMorphDefaulted;
use Tests\Fixtures\TestModel;
use Throwable;

class CustomizableMigrationTest extends TestCase
{
    protected Container $container;
    protected MockInterface $schema;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();

        SchemaFacade::setFacadeApplication($this->container);

        TestModel::$useTable = null;
        TestModel::$useCasts = [];
        TestModel::$useFillable = [];
        TestModel::$useGuarded = [];
        TestModel::$useHidden = [];
        TestModel::$useVisible = [];

        TestMigration::$callMethod = false;

        TestModel::$migration = TestMigration::class;
    }

    protected function tearDown(): void
    {
        m::close();
    }

    #[Test]
    public function creates_columns(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->up();
    }

    #[Test]
    public function creates_table_with_custom_table_name(): void
    {
        TestModel::$useTable = 'foo';

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->withArgs(function (string $table): bool {
            static::assertSame('foo', $table);
            return true;
        });

        TestModel::migration()->up();
    }

    #[Test]
    public function creates_columns_bypasses_callback(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();
        $blueprint->expects('unexpected')->never();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->with(fn($table) => $table->unexpected())->up();
    }

    #[Test]
    public function creates_column_with_callback(): void
    {
        TestMigration::$callMethod = true;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();
        $blueprint->expects('fourthCall')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->once()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration(fn($table) => $table->firstCall())
            ->with(fn($table) => $table->secondCall())
            ->with(fn($table) => $table->thirdCall(), fn($table) => $table->fourthCall())
            ->up();
    }

    #[Test]
    public function morphs_throws_if_called_twice(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('morphs')->with('foo', null)->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));

        $exception = null;

        $schema->expects('create')->once()->withArgs(
            function (string $table, Closure $closure) use ($blueprint, &$exception): bool {
                try {
                    $closure($blueprint);
                } catch (Throwable $e) {
                    $exception = $e;
                }

                return true;
            }
        );

        $migration = new class(TestModel::class) extends CustomizableMigration
        {
            public function create(Blueprint $table): void
            {
                $this->createMorph($table, 'foo');
                $this->createMorph($table, 'foo');
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Using multiple customizable morph calls is unsupported.');

        $migration->up();

        throw $exception;
    }

    #[Test]
    public function morph_nullable_throws_if_called_twice(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('morphs')->with('foo', null)->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));

        $exception = null;

        $schema->expects('create')->once()->withArgs(
            function (string $table, Closure $closure) use ($blueprint, &$exception): bool {
                try {
                    $closure($blueprint);
                } catch (Throwable $e) {
                    $exception = $e;
                }

                return true;
            }
        );

        $migration = new class(TestModel::class) extends CustomizableMigration
        {
            public function create(Blueprint $table): void
            {
                $this->createMorph($table, 'foo');
                $this->createMorph($table, 'foo');
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Using multiple customizable morph calls is unsupported.');

        $migration->up();

        throw $exception;
    }

    public static function useMigrations(): array
    {
        return [
            ['migration' => TestMigrationWithMorph::class, 'index' => null],
            ['migration' => TestMigrationWithMorphDefaulted::class, 'index' => 'custom_index'],
        ];
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_default_from_builder(string $migration, ?string $index): void
    {
        TestModel::$migration = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();
        $blueprint->expects('morphs')->with('foo', $index)->once();
        $blueprint->expects('nullableMorphs')->with('bar', $index)->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));

        $schema->expects('create')->once()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_numeric(string $migration, ?string $index): void
    {
        TestModel::$migration = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('numericMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableNumericMorphs')->with('bar', $index)->twice();
        $blueprint->expects('numericMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableNumericMorphs')->with('bar', 'test_index')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->times(3)->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);

            $closure($blueprint);

            return true;
        });

        TestModel::migration()->morphNumeric->up();
        TestModel::migration()->morph('numeric')->up();
        TestModel::migration()->morph('numeric', 'test_index')->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_uuid(string $migration, ?string $index): void
    {
        TestModel::$migration = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('uuidMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableUuidMorphs')->with('bar', $index)->twice();
        $blueprint->expects('uuidMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableUuidMorphs')->with('bar', 'test_index')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->times(3)->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->morphUuid->up();
        TestModel::migration()->morph('uuid')->up();
        TestModel::migration()->morph('uuid', 'test_index')->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_ulid(string $migration, ?string $index): void
    {
        TestModel::$migration = $migration;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('ulidMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableUlidMorphs')->with('bar', $index)->twice();
        $blueprint->expects('ulidMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableUlidMorphs')->with('bar', 'test_index')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->times(3)->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->morphUlid->up();
        TestModel::migration()->morph('ulid')->up();
        TestModel::migration()->morph('ulid', 'test_index')->up();
    }

    #[Test]
    public function calls_after_up(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->once();
        $schema->expects('table')->times(3)->withArgs(function (string $table, Closure $closure) use ($blueprint
        ): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()
            ->afterUp(fn($table) => $table->firstCall())
            ->afterUp(fn($table) => $table->secondCall(), fn($table) => $table->thirdCall())
            ->up();
    }

    #[Test]
    public function drops_table(): void
    {
        $this->expectNotToPerformAssertions();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('dropIfExists')->with('test_models')->once();

        TestModel::migration()->down();
    }

    #[Test]
    public function calls_before_down(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('table')->times(3)->withArgs(function (string $table, Closure $closure) use ($blueprint
        ): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        $schema->expects('dropIfExists')->with('test_models')->once();

        TestModel::migration()
            ->beforeDown(fn($table) => $table->firstCall())
            ->beforeDown(fn($table) => $table->secondCall(), fn($table) => $table->thirdCall())
            ->down();
    }

    #[Test]
    public function calls_boot_method(): void
    {
        $this->expectNotToPerformAssertions();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->with('test', m::type(Closure::class))->once();

        new Fixtures\TestMigrationWithBoot(TestModel::class);
    }
}
