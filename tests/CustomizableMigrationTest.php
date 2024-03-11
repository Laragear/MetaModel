<?php

namespace Tests;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\TestMigration;
use Tests\Fixtures\TestMigrationWithMorph;
use Tests\Fixtures\TestModel;

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
        $blueprint->expects('createCall')->twice();
        $blueprint->expects('addCall')->twice();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->twice()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->with(fn($table) => $table->addCall())->up();
        TestModel::migration(fn($table) => $table->addCall())->up();
    }

    #[Test]
    public function morphs_default_from_builder(): void
    {
        TestModel::$migration = TestMigrationWithMorph::class;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();
        $blueprint->expects('morphs')->with('foo', null)->once();
        $blueprint->expects('nullableMorphs')->with('bar', null)->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));

        $schema->expects('create')->once()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->up();
    }

    #[Test]
    public function morphs_to_numeric(): void
    {
        TestModel::$migration = TestMigrationWithMorph::class;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->twice();
        $blueprint->expects('numericMorphs')->with('foo', null)->twice();
        $blueprint->expects('nullableNumericMorphs')->with('bar', null)->twice();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->twice()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);

            $closure($blueprint);

            return true;
        });

        TestModel::migration()->morphNumeric->up();
        TestModel::migration()->morph('numeric')->up();
    }

    #[Test]
    public function morphs_to_uuid(): void
    {
        TestModel::$migration = TestMigrationWithMorph::class;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->twice();
        $blueprint->expects('uuidMorphs')->with('foo', null)->twice();
        $blueprint->expects('nullableUuidMorphs')->with('bar', null)->twice();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->twice()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->morphUuid->up();
        TestModel::migration()->morph('uuid')->up();
    }

    #[Test]
    public function morphs_to_ulid(): void
    {
        TestModel::$migration = TestMigrationWithMorph::class;

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->twice();
        $blueprint->expects('ulidMorphs')->with('foo', null)->twice();
        $blueprint->expects('nullableUlidMorphs')->with('bar', null)->twice();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->twice()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->morphUlid->up();
        TestModel::migration()->morph('ulid')->up();
    }

    #[Test]
    public function calls_after_up(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('create')->once();
        $schema->expects('table')->once()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        TestModel::migration()->afterUp(fn($table) => $table->createCall())->up();
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
        $blueprint->expects('afterDownCall')->once();

        $this->container->instance('db.schema', $schema = m::mock(SchemaBuilder::class));
        $schema->expects('table')->once()->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_models', $table);
            $closure($blueprint);

            return true;
        });

        $schema->expects('dropIfExists')->with('test_models')->once();

        TestModel::migration()->beforeDown(fn($table) => $table->afterDownCall())->down();
    }
}
