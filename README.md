# Meta Model
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/meta-model.svg)](https://packagist.org/packages/laragear/meta-model)
[![Latest stable test run](https://github.com/Laragear/MetaModel/workflows/Tests/badge.svg)](https://github.com/Laragear/MetaModel/actions)
[![codecov](https://codecov.io/gh/Laragear/MetaModel/graph/badge.svg?token=fF74Rp0uWn)](https://codecov.io/gh/Laragear/MetaModel)
[![Maintainability](https://qlty.sh/badges/0678bc7e-7900-4e17-9038-ccc0e8fb2b56/maintainability.svg)](https://qlty.sh/gh/Laragear/projects/MetaModel)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_MetaModel&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_MetaModel)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/13.x/octane#introduction)

Let other developers customize your package model and migrations.

```php
namespace Vendor\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomMigration;
use Laragear\MetaModel\HasCustomization;

class MyPackageModel extends Model
{
    use HasCustomization;
    
    protected static function migration(): string
    {
        return CustomMigration::make(function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        })
    }
}
```

> [!TIP]
> 
> Did you come here from a package? You probably want to read the [DATABASE.md](DATABASE.md) file instead.

## Keep this package free

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FMetaModel&hashtags=PHP,Laravel)**

## Requirements

- PHP 8.3 or later
- Laravel 12 or later

## Installation

Fire up Composer and require it into your package:

```bash
composer require laragear/meta-model
```

## Customizing models

When you create a model in your package, your end-developers won't be able to modify it unless they create a repository that manually creates a model, which is cumbersome. Instead, you can add the `HasCustomization` trait to your model and let the developer modify the model when is instanced with a simple callback.

```php
namespace Vendor\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\HasCustomization;

class Car extends Model
{
    use HasCustomization;
    
    // ...
}
```

> [!TIP]
> 
> The trait methods are marked as "internal" to avoid appearing on the end-developer IDE.

From there, the end-developer can customize the model by setting a callback to the `customize()` method. The callback  receives the model instance. For example, they can do this in the `bootstrap/app.php` file, through the `booted()` method.

```php
use Vendor\Package\Models\Car;

use Illuminate\Foundation\Application;

return Application::booted(function () {

    Car::customize(function ($model) {
        $model->setTable('vendor_cars');
        $model->setConnection('read-database');
    });
    
})->create();
```

## Custom Migration

You may use the `makeMigrations()` method of the trait to create an already-made migration. In other words, the end-developers receive a working migration that can modify or extend at their leisure.

Simply use the `make()` method of the `Laragear\MetaModel\CustomMigration` class with a callback to create the table. The table name is retrieved automatically from the Model `getTable()` method, and it will be correctly dropped when invoking `down()` later in the migration file.

```php
namespace Vendor\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomMigration;
use Laragear\MetaModel\HasCustomization;
use MyVendor\MyPackage\Models\Car;

class Car extends Model
{
    use HasCustomization;
    
    public static function makeMigrations(): CustomMigration|array
    {
        return CustomMigration::make(function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }
}
```

> [!TIP]
> 
> Inside the callback, `$this` is bound to the `CustomMigration` instance, not the model.

Once done, create the migration file, like `0000_00_00_000000_create_cars_table.php`. Instead of returning a class that extends the default Laravel migration, we use our model and the `migration()` method.

```php
// database/migrations/0000_00_00_000000_create_cars_table.php
use Vendor\Package\Models\Car;

return Car::migration();
```

### Multiple migrations

If you require to handle multiple migrations for a model, simple return an `array` of migrations, and separate each Custom migration using the `table` argument. This is not necessary for the default migration, only for the additional ones.

```php
namespace Vendor\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomMigration;
use Laragear\MetaModel\HasCustomization;
use MyVendor\MyPackage\Models\Car;

class Car extends Model
{
    use HasCustomization;
    
    public static function makeMigrations(): CustomMigration|array
    {
        return [
            CustomMigration::make(function (Blueprint $table) {
                // ...
            }),
            CustomMigration::make(function (Blueprint $table) {
                // ...
            }, table: 'car_repairs'),
        ];
    }
}
```

To return a non-default migration, simply use the `migration()` method with the respective table name. If no name is issued, the "default" migration (that uses the Model defined table) will be returned. 

```php
// database/migrations/0000_00_00_000000_create_cars_table.php
use MyVendor\MyPackage\Models\Car;

return Car::migration();
```

```php
// database/migrations/0000_00_00_000000_create_car_repairs_table.php
use MyVendor\MyPackage\Models\Car;

return Car::migration('car_repairs');
```

### Morphs

> [!CAUTION]
> 
> Morphs are only supported for a single relation. Multiple morphs relations on a single table is **highly discouraged**. 

If your migration requires morph relationships, you will find that end-developers won't always have the same key type in their application to associate with. This problem can be fixed by using the `createMorph()` or `createNullableMorph()` method with the `Blueprint` instance and the name of the morph type.

```php
use Laragear\MetaModel\CustomMigration;

public static function migration(): CustomMigration
{
    return CustomMigration::make(function (Blueprint $table) {
        $table->id();
        
        $this->createMorph($table, 'ownable');
        
        $table->string('manufacturer');
        $table->string('model');
        $table->tinyInteger('year');
        
        $table->timestamps();
    });
}
```

This will let the end-developer to change the morph type through the `morph()` method if needed. For example, if he's using ULID morphs for the target models, he may set it in one line:

```php
// database/migrations/0000_00_00_000000_create_cars_table.php
use Vendor\Package\Models\Car;

return Car::migration()->morph('ulid', 'custom_index_name');
```

#### Default index name

You may also set a custom index name for the morph. It will be used as a default, unless the end-developer overrides it manually.

```php
use Laragear\MetaModel\CustomMigration;

public static function migration(): CustomMigration
{
    return CustomMigration::make(function (Blueprint $table) {
        // ...
        
        $this->createMorphRelation($table, 'ownable', 'ownable_table_index');
    }
}
```

```php
// database/migrations/0000_00_00_000000_create_cars_table.php
use Vendor\Package\Models\Car;

// Uses "custom_index_name" as index name
return Car::migration()->morph('ulid', 'custom_index_name');

// Uses "ownable_table_index" as index name
return Car::migration()->morph('ulid');
``` 

### After Up & Before Down

An end-developer can execute logic after the table is created, and before the table is dropped, using the `afterUp()` and `beforeDown()` methods, respectively. This allows the developer to run enhance the table, or avoid failing migrations due to dependencies (like linked columns, views or else). 

For example, the end-developer can use these methods to create foreign column references, and remove them before dropping the table.

```php
use MyVendor\MyPackage\Models\Car;
use Illuminate\Database\Schema\Blueprint;

return Car::migration()
    ->afterUp(function (Blueprint $table) {
        $table->foreign('manufacturer')->references('name')->on('manufacturers');
    })
    ->beforeDown(function (Blueprint $table) {
         $table->dropForeign('manufacturer');
    });
```

> [!IMPORTANT]
> 
> The `afterUp()` and `beforeDown()` adds callbacks to the migration, it doesn't replace them.

## Package documentation

If you plan to add migrations to your package, you may also want to copy-and-paste the [DATABASE.md](DATABASE.md) file in your package root. This way developers will know how to use your model and migrations. Alternatively, you may also just copy its contents, or link back to this repository.

For convenience, you may execute this from your project root:

```shell
cp vendor/laragear/meta-model/DATABASE.md ./DATABASE.md
```

## Laravel Octane compatibility

- There are no singletons using a stale application instance.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- Trait static properties are only written once by end-developer.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2025 Laravel LLC.
