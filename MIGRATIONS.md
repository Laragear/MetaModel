# Migration

This package comes with a very hands-off approach for migrations. If you check the new migrations published in `database/migrations`, you will find something very similar to this:

```php
// database/migrations/2022_01_01_193000_create_cars_migration.php
use Vendor\Package\Models\Car;

return Car::migration();
```

Worry not, the migration will still work. It has been _simplified_ for easy customization.

## Adding columns

To add columns to the migration, add a callback to the `migration()` method that receives the table blueprint.

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration(function (Blueprint $table) {
    $table->boolean('is_cool')->default(true);
    $table->string('color');
});
```

> [!INFO]
> 
> If your package doesn't support additional tables, the callback never executes. Refer to the package documentation if adding columns is enabled or not. 

### Relationships

If the package supports it, you may add relationships through their proper migration columns. For example, if we want to add the `driver` relationship to the model, we can use the native `resolveRelationUsing()` on your `AppServiceProvider::boot()`. 

```php
namespace App\Providers;

use App\Models\Owner;
use Illuminate\Support\ServiceProvider;
use Vendor\Package\Models\Driver;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Car::resolveRelationUsing('driver', function (Car $car) {
            return $car->belongsTo(Driver::class, 'driver_id')
        })
    }
}
```

In your migration, you should be able to add the required table for your model using `addColumns()`.

```php
use App\Models\Driver;
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration(function (Blueprint $table) {
    // ...
    
    $table->foreignIdFor(Driver::class);
});
```

## After Up & Before Down

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

### Morphs

Some packages will create a morph relation automatically to easily handle default relationship across multiple models. For example, a morph migration to support an `owner` being either one of your models `Company` or `Person`.

```php
use Laragear\Package\Models\Car;

$car = Car::find(1);

$owners = $car->owner; // App/Models/Company or App/Models/Person
```

You may find yourself with models that use UUID, ULID or other types of primary keys, but with a migration creating morphs for integer primary keys.

To change the morph type, use the `morph...` property access preferably, or the `morph()` method with `numeric`, `uuid` or `ulid` if you need to also set an index name (in case your database engine doesn't play nice with large ones).

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()->morphUuid;

return Car::migration()->morph('uuid', 'shorter_morph_index_name');
```

## Custom table name

By default, tables are set using the model name in plural. If you want to change the table name from the standard, set it using the `$useTable` static property of the target Model. You should do this on the `register()` method of your `AppServiceProvider`.

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laragear\Package\Models\Model;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Model::$useTable = 'my_custom_table';
    }
}
```

### Configuring the model

All customizable models can be configured with additional fillable, guarded, hidden, visible and appended attributes. These are _merged_ with the original configuration of the model itself, so changes are not destructive. 

Customize the model using the available static properties:

- `$useConnection`: The connection name to use.
- `$useCasts`: The casts attributes to merge.
- `$useFillable`: The fillable attributes to merge.
- `$useGuarded`: The guarded attributes to merge.
- `$useHidden`: The hidden attributes to merge.
- `$useVisible`: The visible attributes to merge.
- `$useAppends`: The appended attributes to merge.

```php
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Vendor\Package\Models\Car;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Car::$useCasts = [
            'is_cool' => 'boolean',
            'colors' => AsEncryptedCollection::class,
        ];
        
        Car::$useHidden = ['colors'];
    }
}
```

> [!IMPORTANT]
> 
> If you're using `$useAppends`, ensure you also set `$useCasts` for attributes that are not part of the model itself. 
