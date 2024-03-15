# Meta Model
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/meta-model.svg)](https://packagist.org/packages/laragear/meta-model)
[![Latest stable test run](https://github.com/Laragear/MetaModel/workflows/Tests/badge.svg)](https://github.com/Laragear/MetaModel/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/MetaModel/branch/1.x/graph/badge.svg?token=5COE8X0JMJ)](https://codecov.io/gh/Laragear/MetaModel)
[![Maintainability](https://api.codeclimate.com/v1/badges/c183a169a2e4419c9239/maintainability)](https://codeclimate.com/github/Laragear/MetaModel/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_MetaModel&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_MetaModel)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/11.x/octane#introduction)

Let other developers customize your package model and migrations.

```php
use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\CustomizableModel;
use MyVendor\MyPackage\Migrations\MyMigration;

class MyPackageModel extends Model
{
    use CustomizableModel;
    
    protected static function migrationClass(): string
    {
        return MyMigration::class    
    }
}
```

> [!TIP]
> 
> Did you come here from a package? You probably want to read the [MIGRATIONS.md](MIGRATIONS.md) file instead.

## Keep this package free

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FMetaModel&hashtags=PHP,Laravel)**

## Requirements

- Laravel 10 or later

## Installation

Fire up Composer and require it into your package:

```bash
composer require laragear/meta-model
```

## Customizing models

Most of the time, your users will want to customize the models and migrations in your package. For example, they would want to add columns and cast them to specific data types, or modify which properties are hidden. This can be done with a model that incorporates the `CustomizableModel` trait.

```php
namespace Vendor\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\CustomizableModel;
use Vendor\Package\Migrations\ModelMigration;

class MyModel extends Model
{
    use CustomizableModel;
    
    protected static function migrationClass(): string
    {
        return ModelMigration::class;
    }
}
```

From there, the end-developer can customize the model using the available static properties:

- `$useTable`: A custom table name to use.
- `$useCasts`: The casts attributes to merge.
- `$useFillable`: The fillable attributes to merge.
- `$useGuarded`: The guarded attributes to merge.
- `$useHidden`: The hidden attributes to merge.
- `$useVisible`: The visible attributes to merge.
- `$useAppends`: The appends attributes to merge.

All of these static properties, except for `$useTable`, accept a Closure that receives the model and returns an array of attributes. The end-developer should modify these properties in the `boot()` method of the `AppServiceProvider`.

```php
namespace App\Providers;

use MyVendor\MyPackage\Models\Car;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Car::$useCasts = ['is_new' => 'boolean'];
    }
}
```

## Customizable Migration

To allow customizable migrations, create a standard migration file, but, instead of returning a class that extends the default `Migration` class, return a `migration()` call to your model class.

Let's explain this is awesome.

For example, let's say we want to create a migration for a Car model. We will create a class that extends the `CustomizableMigration` class. From there, the table schema will be handled in the `create()` method.

```php
namespace MyVendor\MyPackage\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;
use MyVendor\MyPackage\Models\Car;

class CarsMigration extends CustomizableMigration
{
    protected function create(Blueprint $table)
    {
        $table->id();
        
        $table->string('manufacturer');
        $table->string('model');
        $table->tinyInteger('year');
        
        $table->timestamps();
    }
}
```

After defining our default migration class, we need tell the Model where is in the `$migration` static property:

```php
namespace MyVendor\MyPackage\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\CustomizableModel;
use MyVendor\MyPackage\Migrations\CarsMigration;

class Car extends Model
{
    use CustomizableModel;
    
    protected static function migrationClass(): string
    {
        reutnr CarsMigration::class
    };
}
```

Once then, we can create the migration file `0000_00_00_000000_create_cars_table.php`. Instead of returning a class that extends the default Laravel migration, we use our model and the `migration()` method.

```php
// database/migrations/0000_00_00_000000_create_cars_table.php

use MyVendor\MyPackage\Models\Car;
use Illuminate\Database\Schema\Blueprint;

return Car::migration();
```

### Booting 

You can run custom logic when the migration is instanced using the `boot()` method. 

```php
namespace MyVendor\MyPackage\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;
use MyVendor\MyPackage\Models\Car;

class CarsMigration extends CustomizableMigration
{
    protected function boot() : void
    {
        if (app()->isUnitTesting()) {
            Car::$useConnection = env('DB_CONNECTION');        
        }
    }

    protected function create(Blueprint $table)
    {
        $table->id();
        
        $table->string('manufacturer');
        $table->string('model');
        $table->tinyInteger('year');
        
        $table->timestamps();
    }
}
```

> [!CAUTION]
> 
> The `boot()` method runs every time the migration is instanced. Ensure the method effects are idempotent when required.

### Adding Custom Columns

You may want to let the end-developer to add additional columns to the migration. For that, just call `addColumns()` anywhere inside the `create()` method, ensuring you pass the `Blueprint` instance. A great place to call this is just before the `timestamps()` or after the primary key.

```php
namespace MyVendor\MyPackage\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;

abstract class CarsMigration extends CustomizableMigration
{
    protected function create(Blueprint $table)
    {
        $table->id();
        
        $table->string('manufacturer');
        $table->string('model');
        $table->tinyInteger('year');
        
        $this->addColumns($table);
        
        $table->timestamps();
    }
}
```

After that, in your migration file, add an empty callback to the `migration()` method, or use the `with()` method, so the end-developer knows he can extend the table schema. 

```php
use MyVendor\MyPackage\Models\Car;
use Illuminate\Database\Schema\Blueprint;

return Car::migration(function (Blueprint $table) {
    // Add your custom columns here.
    // Don't forget to add casts to the model if necessary!
})
```

> [!TIP]
> 
> If you don't want to support additional columns, it's fine. If the end-developer adds a callback, it won't be executed regardless. 

### Morphs

If your migration requires morph relationships, you will find that end-developers won't always have the same key type in their application. This problem can be fixed by using the `createMorph()` or `createNullableMorph()` method with the `Blueprint` instance and the name of the morph type.

```php
protected function create(Blueprint $table)
{
    $table->id();
    
    $this->createMorphRelation($table, 'ownable');
    
    $table->string('manufacturer');
    $table->string('model');
    $table->tinyInteger('year');
    
    $table->timestamps();
}
```

This will let the end-developer to change the morph type through the `morph()` method if needed. For example, if he's using ULID morphs for the target models, he may set it in one line:

```php
use MyVendor\MyPackage\Models\Car;
use Illuminate\Database\Schema\Blueprint;

return Car::migration()->morph('ulid', 'custom_index_name');
```

### After Up & Before Down

The `CustomizableMigration` contains two methods, `afterUp()` and `beforeDown()`. The first is executed after the table is created, while the latter is executed before the table is dropped. This allows the developer to run custom logic to enhance its migrations, or avoid failing migrations.

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

## Package documentation

If you plan to add this to your package, you may also want to copy-and-paste the [MIGRATIONS.md](MIGRATIONS.md) file in your package. This way developers will know how to use your model and migrations. Alternatively, you may also just copy its contents, or link back to this repository.

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

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2024 Laravel LLC.
