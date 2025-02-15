<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\CustomMigration;
use Laragear\MetaModel\HasCustomization;

class TestCustomizableModelWithMigration extends Model
{
    use HasCustomization;

    public static $create;

    public static function migration(): CustomMigration
    {
        return CustomMigration::create(static::$create);
    }
}
