# Migrations and Model customization

The library you have installed comes with a very hands-off approach to the included Models and Migrations. You can modify them as you wish with simple callbacks that should run when your application boots.

For example, the most common scenario of **changing a table name** for a given model can be done with the customize() method in your `bootstrap/app.php` file or `App\Providers\AppServiceProvider` class. The migration will automatically pick up new table name.

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Vendor\Package\Models\Driver;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(fn () {
        // Change the model table
        Car::customize(fn ($model) => $model->setTable('my_custom_car');
    })->create();
```

If you require a more in-deep customization of the models included in the library, just read further.

## Model customization

As explained before, the `customize()` method accepts a callback that receives the freshly instanced model. You can edit the model anyway you want, from changing the model table and connection, to hide some attributes or add casts. The changes will be applied each time the model is instanced in your application.

Preferably, you would do this in your `bootstrap/app.php` or `App\Providers\AppServiceProvider`. 

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Vendor\Package\Models\Driver;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(function () {
        // Customize the model
        Car::customize(function (Car $model) {
            $model->setTable('my_custom_car');
            $model->setConnection('readonly-mysql');
            
            $model->setHidden('private_notes');
        })
    })->create();
```

> [!TIP]
>
> For your convenience, the underlying Migration will automatically pick up the table and connection you set in the Model.

## Migration customization

Migrations for the library models are simplified. If you check your `database/migrations` directory, you may find a file with the contents very similar to this:

```php
// database/migrations/2022_01_01_193000_create_cars_migration.php
use Vendor\Package\Models\Car;

return Car::migration();
```

Worry not, the migration will still work. It has been _simplified_ for easy customization without having to accidentally break the required columns.

### Adding columns

To add columns to the migration, use the `with()` method. with a callback that receives the table blueprint to [crete a table](https://laravel.com/docs/12.x/migrations#creating-tables).

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()->with(function (Blueprint $table) {
    $table->boolean('is_cool')->default(true);
    $table->string('color');
});
```

> [!NOTE]
>
> The columns you add will be created _after_ the package adds its own columns to the Blueprint, adding them to the end of the table.

### Adding relationships

Depending on the Model, you may add a relationship like a _Belongs To_ by adding proper migration columns. For example, let's add a `driver` relation for the `Car` model so we can associate them through the [`foreignIdFor()`](https://laravel.com/docs/migrations#column-method-foreignIdFor) method.

```php
use App\Models\Driver;
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration(function (Blueprint $table) {
    // ...
    
    // Add the column needed for the `driver` relationship.
    $table->foreignIdFor(Driver::class);
});
```

After that, we can use the native `resolveRelationUsing()` method from the Eloquent Model to set the proper relationship type:

```php
use App\Models\Driver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laragear\Package\Models\Car;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(function () {
        // Add the relationship.
        Car::resolveRelationUsing('driver', function (Car $car) {
            return $car->belongsTo(Driver::class, 'driver_id')
        })
    })->create();
```

### After Up & Before Down

If you need to execute logic after creating the table, or before dropping it, use the `afterUp()` and `beforeDown()` methods, respectively.

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()
    ->afterUp(function (Blueprint $table) {
        $table->foreignId('sociable_id')->references('id')->on('users');
    })
    ->beforeDown(function (Blueprint $table) {
        $table->dropForeign('sociable_id');
    });
```

This is great if, for example, you have set up foreign references to other columns, or created indexes that need to be created and dropped separately.

## Morphs

The library _may_ create a morph relation automatically, with the intent to easily handle default relationship across multiple different models. For example, a morph migration to support an `owner` being either one of your models `App\Models\Company` or `App\Models\Person`.

```php
use Laragear\Package\Models\Car;

$car = Car::find(1);

$owners = $car->owner; // App/Models/Company or App/Models/Person
```

You may find yourself with your models using a primary key type different to the one use by the library model. For example, your `App\Models\Company` or `App\Models\Person` models using UUID, while the migration morph type uses integers.

If that's your case, you can change the library morph type with the `morph...` property access (preferably), or the `morph()` method with `numeric`, `uuid` or `ulid` if you need to also set an index name (in case your database engine doesn't play nice with large ones).

For example, you can change the morph type of the `Car` migration to match the UUID type for the `Company` and `Person` models.

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()->morphUuid;

return Car::migration()->morph('uuid', 'shorter_morph_index_name');
```
