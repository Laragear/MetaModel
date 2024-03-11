<?php

namespace Laragear\MetaModel;

use Closure;
use Error;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use function sprintf;
use function strtolower;

/**
 * @property-read static $morphNumeric
 * @property-read static $morphUuid
 * @property-read static $morphUlid
 */
abstract class CustomizableMigration extends Migration
{
    /**
     * The table to use for the migration.
     *
     * @var string
     */
    protected string $table;

    /**
     * Create a new Customizable Migration instance.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)|null  $with
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)|null  $afterUp
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)|null  $beforeDown
     * @param  "numeric"|"uuid"|"ulid"|""  $morphType
     * @param  string|null  $morphIndexName
     */
    public function __construct(
        string $model,
        protected ?Closure $with = null,
        protected ?Closure $afterUp = null,
        protected ?Closure $beforeDown = null,
        protected string $morphType = '',
        protected ?string $morphIndexName = null,
    )
    {
        $this->table = (new $model)->getTable();
    }

    /**
     * Create the table columns.
     */
    abstract public function create(Blueprint $table): void;

    /**
     * Execute a callback from the developer to add more columns in the table, if any.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @return void
     */
    protected function addColumns(Blueprint $table): void
    {
        with($table, $this->with);
    }

    /**
     * Sets the morph type of the migration.
     *
     * @param  "numeric"|"uuid"|"ulid"  $type
     * @param  string|null  $indexName
     * @return $this
     */
    public function morph(string $type, string $indexName = null): static
    {
        $this->morphType = $type;
        $this->morphIndexName = $indexName;

        return $this;
    }

    /**
     * Add additional columns to the table.
     *
     * @param  \Closure(\Illuminate\Database\Schema\Blueprint $table):void  $callback
     * @return $this
     */
    public function with(Closure $callback): static
    {
        $this->with = $callback;

        return $this;
    }

    /**
     * Execute the callback after the "up" method.
     *
     * @param  \Closure(\Illuminate\Database\Schema\Blueprint $table):void  $callback
     * @return $this
     */
    public function afterUp(Closure $callback): static
    {
        $this->afterUp = $callback;

        return $this;
    }

    /**
     * Execute the callback before the "down" method.
     *
     * @param  \Closure(\Illuminate\Database\Schema\Blueprint $table):void  $callback
     * @return $this
     */
    public function beforeDown(Closure $callback): static
    {
        $this->beforeDown = $callback;

        return $this;
    }

    /**
     * Create a new morph relation.
     */
    protected function createMorph(Blueprint $table, string $name): void
    {
        match (strtolower($this->morphType)) {
            'numeric' => $table->numericMorphs($name, $this->morphIndexName),
            'uuid' => $table->uuidMorphs($name, $this->morphIndexName),
            'ulid' => $table->ulidMorphs($name, $this->morphIndexName),
            default => $table->morphs($name, $this->morphIndexName)
        };
    }

    /**
     * Create a new nullable morph relation.
     */
    protected function createNullableMorph(Blueprint $table, string $name): void
    {
        match (strtolower($this->morphType)) {
            'numeric' => $table->nullableNumericMorphs($name, $this->morphIndexName),
            'uuid' => $table->nullableUuidMorphs($name, $this->morphIndexName),
            'ulid' => $table->nullableUlidMorphs($name, $this->morphIndexName),
            default => $table->nullableMorphs($name, $this->morphIndexName)
        };
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
     * Run the migrations.
     *
     * @internal
     */
    public function up(): void
    {
        Schema::create($this->table, $this->create(...));

        if ($this->afterUp) {
            Schema::table($this->table, $this->afterUp);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @internal
     */
    public function down(): void
    {
        if ($this->beforeDown) {
            Schema::table($this->table, $this->beforeDown);
        }

        Schema::dropIfExists($this->table);
    }
}
