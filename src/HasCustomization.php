<?php

namespace Laragear\MetaModel;

use Closure;
use Illuminate\Support\Str;
use RuntimeException;
use function array_merge;
use function array_unique;
use function class_basename;
use function is_array;

/**
 * @internal
 */
trait HasCustomization
{
    /**
     * The fillable attributes to merge with the default model.
     *
     * @var (\Closure(static):void)|null
     */
    protected static ?Closure $useCustomization;

    /**
     * Initialize the current model.
     */
    protected function initializeHasCustomization(): void
    {
        isset(static::$useCustomization) && (static::$useCustomization)($this);
    }

    /**
     * Sets a callback to customize the model on initialization.
     *
     * @param  (\Closure(static):void)|null  $callback
     */
    public static function customize(?Closure $callback): void
    {
        static::$useCustomization = $callback;
    }

    /**
     * Return the customizable migration instance.
     *
     * @return \Laragear\MetaModel\CustomMigration<static>
     */
    public static function migration(): CustomMigration
    {
        throw new RuntimeException('The '.static::class.' has not implemented customizable migrations.');
    }
}
