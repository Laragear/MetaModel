<?php

namespace Laragear\MetaModel;

use Closure;
use Error;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use function array_push;
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
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)[]  $with
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)[]  $afterUp
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void)[]  $beforeDown
     * @param  "numeric"|"uuid"|"ulid"|""  $morphType
     * @param  string|null  $morphIndexName
     */
    public function __construct(
        string $model,
        protected array $with = [],
        protected array $afterUp = [],
        protected array $beforeDown = [],
        protected string $morphType = '',
        protected ?string $morphIndexName = null,
    )
    {
        $this->table = (new $model)->getTable();

        $this->boot();
    }

    /**
     * Run additional logic when the migration is instanced.
     *
     * @return void
     */
    protected function boot(): void
    {
        //
    }

    /**
     * Create the table columns.
     */
    abstract public function create(Blueprint $table): void;

    /**
     * Execute stored callbacks using the table Blueprint instance.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @return void
     */
    protected function addColumns(Blueprint $table): void
    {
        foreach ($this->with as $callback) {
            $callback($table);
        }
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

        foreach ($this->afterUp as $callback) {
            Schema::table($this->table, $callback);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @internal
     */
    public function down(): void
    {
        foreach ($this->beforeDown as $callback) {
            Schema::table($this->table, $callback);
        }

        Schema::dropIfExists($this->table);
    }
}
