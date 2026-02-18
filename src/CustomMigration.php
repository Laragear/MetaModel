<?php

namespace Laragear\MetaModel;

use BadMethodCallException;
use Closure;
use Error;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use function array_push;
use function class_exists;
use function data_get;
use function debug_backtrace;
use function is_string;
use function method_exists;
use function sprintf;
use function strtolower;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @property-read static $morphNumeric
 * @property-read static $morphUuid
 * @property-read static $morphUlid
 *
 * @phpstan-consistent-constructor
 */
class CustomMigration extends Migration
{
    /**
     * Create a new Customizable Migration instance.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)|null  $create
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)[]  $with
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)[]  $afterUp
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)[]  $beforeDown
     * @param  "numeric"|"uuid"|"ulid"|""  $morphType
     */
    public function __construct(
        ?string $connection,
        public string $table = '',
        protected ?Closure $create = null,
        protected array $with = [],
        protected array $afterUp = [],
        protected array $beforeDown = [],
        protected string $morphType = '',
        protected ?string $morphIndexName = null,
        protected bool $morphCalled = false,
    )
    {
        if ($connection) {
            $this->connection = $connection;
        }
    }

    /**
     * Create a new morph relation.
     */
    protected function createMorph(Blueprint $table, string $name, ?string $indexName = null): void
    {
        if ($this->morphCalled) {
            throw new BadMethodCallException('Using multiple customizable morph calls is unsupported.');
        }

        $indexName = $this->morphIndexName ?? $indexName;

        match (strtolower($this->morphType)) {
            'numeric' => $table->numericMorphs($name, $indexName),
            'uuid' => $table->uuidMorphs($name, $indexName),
            'ulid' => $table->ulidMorphs($name, $indexName),
            default => $table->morphs($name, $indexName)
        };

        $this->morphCalled = true;
    }

    /**
     * Create a new nullable morph relation.
     */
    protected function createNullableMorph(Blueprint $table, string $name, ?string $indexName = null): void
    {
        if ($this->morphCalled) {
            throw new BadMethodCallException('Using multiple customizable morph calls is unsupported.');
        }

        $indexName = $this->morphIndexName ?? $indexName;

        match (strtolower($this->morphType)) {
            'numeric' => $table->nullableNumericMorphs($name, $indexName),
            'uuid' => $table->nullableUuidMorphs($name, $indexName),
            'ulid' => $table->nullableUlidMorphs($name, $indexName),
            default => $table->nullableMorphs($name, $indexName)
        };

        $this->morphCalled = true;
    }

    /**
     * Sets the morph type of the migration.
     *
     * @param  "numeric"|"uuid"|"ulid"  $type
     * @param  string|null  $indexName
     * @return $this
     */
    public function morph(string $type, ?string $indexName = null): static
    {
        [$this->morphType, $this->morphIndexName] = [$type, $indexName];

        return $this;
    }

    /**
     * Add additional columns to the table.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)  ...$callbacks
     * @return $this
     */
    public function with(Closure ...$callbacks): static
    {
        array_push($this->with, ...$callbacks);

        return $this;
    }

    /**
     * Execute the callback after the "up" method.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)  ...$callbacks
     * @return $this
     */
    public function afterUp(Closure ...$callbacks): static
    {
        array_push($this->afterUp, ...$callbacks);

        return $this;
    }

    /**
     * Execute the callback before the "down" method.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)  ...$callbacks
     * @return $this
     */
    public function beforeDown(Closure ...$callbacks): static
    {
        array_push($this->beforeDown, ...$callbacks);

        return $this;
    }

    /**
     * Dynamically handle property access to the object.
     *
     * @internal
     * @param  string  $name
     * @return $this
     */
    public function __get(string $name)
    {
        return match ($name) {
            'morphNumeric' => $this->morph('numeric'),
            'morphUuid' => $this->morph('uuid'),
            'morphUlid' => $this->morph('ulid'),
            default => throw new Error(sprintf('Undefined property: %s::%s', static::class, $name))
        };
    }

    /**
     * Retrieve the Database Schema Builder with the appropriate connection.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getBuilder(): Builder
    {
        $container = Container::getInstance();

        return method_exists(Builder::class, 'setConnection')
            ? $container->make(Builder::class)->setConnection($this->connection) // @phpstan-ignore-line
            : $container->make(Builder::class, ['connection' => $this->connection]);
    }

    /**
     * Run the migrations.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @internal
     */
    public function up(): void
    {
        $builder = $this->getBuilder();

        $builder->create($this->table, function (Blueprint $blueprint): void {
            // Bind the creation to this migration helper.
            $this->create->call($this, $blueprint);

            // If there is additional columns, add them at the end of the migration.
            foreach ($this->with as $callback) {
                $callback($blueprint);
            }
        });

        foreach ($this->afterUp as $callback) {
            $builder->table($this->table, $callback);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @internal
     */
    public function down(): void
    {
        $builder = $this->getBuilder();

        foreach ($this->beforeDown as $callback) {
            $builder->table($this->table, $callback);
        }

        $builder->dropIfExists($this->table);
    }

    /**
     * Create new customizable migration for an external model.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)  $create
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|null  $model
     * @return static
     *
     * @deprecated Use `make` instead
     * @see static::make()
     */
    public static function create(Closure $create, Model|string|null $model = null): static
    {
        return static::make(...func_get_args());
    }

    /**
     * Create new customizable migration for an external model.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)  $create
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|null  $model
     * @return static
     */
    public static function make(Closure $create, Model|string|null $model = null, ?string $connection = null): static
    {
        // If the developer didn't set the model, we will find its name using a debug backtrace.
        if (!$model) {
            $model = data_get(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3), '1.class');
        }

        if (class_exists($model)) {
            $model = new $model();
        }

        if ($model instanceof Model) {
            return new static($connection ?? $model->getConnectionName(), $model->getTable(), $create);
        }

        return new static($connection, $model, $create);
    }
}
