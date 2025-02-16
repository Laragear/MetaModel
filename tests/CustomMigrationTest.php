<?php

namespace Tests;

use BadMethodCallException;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Laragear\MetaModel\CustomMigration;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

class CustomMigrationTest extends TestCase
{
    /** @var \Illuminate\Database\Schema\Builder&MockInterface */
    protected Builder $schema;

    /** @var \Illuminate\Container\Container&MockInterface */
    protected Container $container;

    /** @var \Illuminate\Database\Eloquent\Model&MockInterface */
    protected MockInterface $model;

    protected function setUp(): void
    {
        $this->schema = m::mock(Builder::class);
        $this->schema->expects('setConnection')->zeroOrMoreTimes()->andReturnSelf();

        $this->container = Container::setInstance(m::mock(Container::class));
        $this->container->expects('make')->withArgs(function (string $class) {
            return $class === Builder::class;
        })->atLeast()->once()->andReturn($this->schema);

        $this->model = m::mock(Model::class);
        $this->model->expects('getTable')->atLeast()->once()->andReturn('test_table');
        $this->model->expects('getConnection')->atLeast()->once()->andReturn(m::mock(Connection::class));
    }

    protected function tearDown(): void
    {
        m::close();
        Container::setInstance();
    }

    public function test_creates_columns_bypasses_callback(): void
    {
        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();

        $this->schema->expects('create')->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
            static::assertSame('test_table', $table);
            $closure($blueprint);

            return true;
        });

        (new CustomMigration($this->model, fn() => true))->with(fn($table) => $table->createCall())->up();
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

        $migration = (new CustomMigration($this->model, function ($table) {
            $this->createMorph($table, 'foo');
            $this->createMorph($table, 'foo');
        }));

        $migration->up();

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

        $migration = (new CustomMigration($this->model, function (Blueprint $table): void {
            $this->createNullableMorph($table, 'foo');
            $this->createNullableMorph($table, 'foo');
        }));

        $migration->up();

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
        $this->expectNotToPerformAssertions();

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->once();
        $blueprint->expects('morphs')->with('foo', $index)->once();
        $blueprint->expects('nullableMorphs')->with('bar', $index)->once();

        $this->schema->expects('create')->once()
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                $closure($blueprint);

                return true;
            });

        (new CustomMigration($this->model, $migration))->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_numeric(Closure $migration, ?string $index): void
    {
        $this->expectNotToPerformAssertions();

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('numericMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableNumericMorphs')->with('bar', $index)->twice();
        $blueprint->expects('numericMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableNumericMorphs')->with('bar', 'test_index')->once();

        $this->schema->expects('create')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                $closure($blueprint);

                return true;
            });

        (new CustomMigration($this->model, $migration))->morphNumeric->up();
        (new CustomMigration($this->model, $migration))->morph('numeric')->up();
        (new CustomMigration($this->model, $migration))->morph('numeric', 'test_index')->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_uuid(Closure $migration, ?string $index): void
    {
        $this->expectNotToPerformAssertions();

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('uuidMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableUuidMorphs')->with('bar', $index)->twice();
        $blueprint->expects('uuidMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableUuidMorphs')->with('bar', 'test_index')->once();

        $this->schema->expects('create')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                $closure($blueprint);

                return true;
            });

        (new CustomMigration($this->model, $migration))->morphUuid->up();
        (new CustomMigration($this->model, $migration))->morph('uuid')->up();
        (new CustomMigration($this->model, $migration))->morph('uuid', 'test_index')->up();
    }

    #[Test]
    #[DataProvider('useMigrations')]
    public function morphs_to_ulid(Closure $migration, ?string $index): void
    {
        $this->expectNotToPerformAssertions();

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('createCall')->times(3);
        $blueprint->expects('ulidMorphs')->with('foo', $index)->twice();
        $blueprint->expects('nullableUlidMorphs')->with('bar', $index)->twice();
        $blueprint->expects('ulidMorphs')->with('foo', 'test_index')->once();
        $blueprint->expects('nullableUlidMorphs')->with('bar', 'test_index')->once();

        $this->schema->expects('create')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                $closure($blueprint);

                return true;
            });

        (new CustomMigration($this->model, $migration))->morphUlid->up();
        (new CustomMigration($this->model, $migration))->morph('ulid')->up();
        (new CustomMigration($this->model, $migration))->morph('ulid', 'test_index')->up();
    }

    public function test_calls_after_up(): void
    {
        $this->expectNotToPerformAssertions();

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();

        $this->schema->expects('create')->once();
        $this->schema->expects('table')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                $closure($blueprint);

                return true;
            });

        (new CustomMigration($this->model, fn() => true))
            ->afterUp(fn($table) => $table->firstCall())
            ->afterUp(fn($table) => $table->secondCall(), fn($table) => $table->thirdCall())
            ->up();
    }

    public function test_drops_table_automatically(): void
    {
        $this->expectNotToPerformAssertions();

        $this->schema->expects('dropIfExists')->with('test_table')->once();

        (new CustomMigration($this->model, fn() => true))->down();
    }

    public function test_calls_before_down(): void
    {
        $this->expectNotToPerformAssertions();

        $blueprint = m::mock(Blueprint::class);
        $blueprint->expects('firstCall')->once();
        $blueprint->expects('secondCall')->once();
        $blueprint->expects('thirdCall')->once();

        $this->schema->expects('table')->times(3)
            ->withArgs(function (string $table, Closure $closure) use ($blueprint): bool {
                $closure($blueprint);

                return true;
            });

        $this->schema->expects('dropIfExists')->with('test_table')->once();

        (new CustomMigration($this->model, fn() => true))
            ->beforeDown(fn($table) => $table->firstCall())
            ->beforeDown(fn($table) => $table->secondCall(), fn($table) => $table->thirdCall())
            ->down();
    }
}
