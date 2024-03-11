<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\CustomizableModel;

class TestModel extends Model
{
    use CustomizableModel;

    public static string $migration = TestMigration::class;

    protected $hidden = ['baz'];
    protected $visible = ['quz'];
    protected $appends = ['qux'];

    protected static function migrationClass(): string
    {
        return static::$migration;
    }
}
