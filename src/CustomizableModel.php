<?php

namespace Laragear\MetaModel;

use Closure;
use Illuminate\Support\Str;
use function array_merge;
use function array_unique;
use function class_basename;
use function is_array;

/**
 * @internal
 */
trait CustomizableModel
{
    /**
     * The table name to use for this customizable model.
     *
     * @var string|null
     */
    public static $useTable;

    /**
     * The casts to merge with the default model.
     *
     * @var (\Closure():array<string, mixed>)|array<string, string>
     */
    public static $useCasts = [];

    /**
     * The fillable attributes to merge with the default model.
     *
     * @var (\Closure():string[])|string[]
     */
    public static $useFillable = [];

    /**
     * The fillable attributes to merge with the default model.
     *
     * @var (\Closure():string[])|string[]
     */
    public static $useGuarded = [];

    /**
     * The hidden attributes to merge with the default model.
     *
     * @var (\Closure():string[])|string[]
     */
    public static $useHidden = [];

    /**
     * The visible attributes to merge with the default model.
     *
     * @var (\Closure():string[])|string[]
     */
    public static $useVisible = [];

    /**
     * The fillable attributes to merge with the default model.
     *
     * @var (\Closure():string[])|string[]
     */
    public static $useAppends = [];

    /**
     * Boot the trait.
     */
    protected static function bootCustomizableModel(): void
    {
        static::$useTable ??= Str::snake(Str::pluralStudly(class_basename(static::class)));
    }

    /**
     * Initialize the trait.
     */
    protected function initializeCustomizableModel(): void
    {
        $this->table = static::$useTable;

        $resolve = static function (Closure|array $value, $model): array {
            return is_array($value) ? $value : ($value)($model);
        };

        $this->mergeCasts($resolve(static::$useCasts, $this));
        $this->mergeFillable($resolve(static::$useFillable, $this));

        $this->totallyGuarded()
            ? $this->guarded = $resolve(static::$useGuarded, $this)
            : $this->mergeGuarded($resolve(static::$useGuarded, $this));

        static::$useHidden && $this->setHidden(
            array_unique(array_values(array_merge($this->hidden, $resolve(static::$useHidden, $this))))
        );

        static::$useVisible && $this->setVisible(
            array_unique(array_values(array_merge($this->visible, $resolve(static::$useVisible, $this))))
        );

        static::$useAppends && $this->setAppends(
            array_unique(array_values(array_merge($this->appends, $resolve(static::$useAppends, $this))))
        );
    }

    /**
     * Return the customizable migration class name.
     *
     * @return class-string<\Laragear\MetaModel\CustomizableMigration>
     */
    abstract protected static function migrationClass(): string;

    /**
     * Return a new customizable migration instance.
     *
     * @param  (\Closure(\Illuminate\Database\Schema\Blueprint $table):void) ...$with
     */
    public static function migration(Closure ...$with): CustomizableMigration
    {
        return (new (static::migrationClass())(static::class, $with));
    }
}
