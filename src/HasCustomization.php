<?php

namespace Laragear\MetaModel;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ValueError;
use function array_any;
use function array_key_first;
use function array_merge;
use function array_unique;
use function class_basename;
use function in_array;
use function is_array;
use function is_iterable;
use function is_string;

trait HasCustomization
{
    /**
     * The fillable attributes to merge with the default model.
     *
     * @var (\Closure(static):void)|null
     *
     * @internal
     */
    protected static ?Closure $useCustomization;

    /**
     * Initialize the current model.
     *
     * @internal
     */
    protected function initializeHasCustomization(): void
    {
        isset(static::$useCustomization) && (static::$useCustomization)($this);
    }

    /**
     * Sets a callback to customize the model on initialization.
     *
     * @param  (\Closure(static):void)|string|null  $callback
     */
    public static function customize(Closure|string|null $callback): void
    {
        if (is_string($callback)) {
            $callback = static function (Model $model) use ($callback): void {
                $model->setTable($callback);
            };
        }

        static::$useCustomization = $callback;
    }

    /**
     * Creates one or many migrations for this Model.
     *
     * @return \Laragear\MetaModel\CustomMigration<static>|array<\Laragear\MetaModel\CustomMigration<static>>
     *
     * @internal
     */
    protected static function makeMigration(): CustomMigration|array
    {
        throw new RuntimeException('The '.static::class.' has not implemented customizable migrations.');
    }

    /**
     * Return customizable migration instances.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint):void)|string  $table
     * @return \Laragear\MetaModel\CustomMigration<static>
     */
    public static function migration(Closure|string $table = ''): CustomMigration
    {
        [$callback, $table] = match (true) {
            $table instanceof Closure => [$table, (new static)->getTable()],
            default => [null, $table ?: (new static)->getTable()],
        };

        // If there is no table name, we'll use this Model table name so it always picks
        // the default migration by default. Otherwise, we will find the migration that
        // coincides with the table name the dev issued and return it to the migrator.
        foreach (Arr::wrap(static::makeMigration()) as $migration) {
            if ($table === $migration->table) {
                if ($callback) {
                    $migration->with($callback);
                }

                return $migration;
            }
        }

        throw new InvalidArgumentException("The migration [$table] does not exist.");
    }
}
