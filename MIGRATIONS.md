# Migration

This package comes with a very hands-off approach for migrations. If you check the new migrations published in `database/migrations`, you will find something very similar to this:

```php
use Laragear\Package\Models\Car;

return Car::migration();
```

Worry not, the migration will still work. It has been _simplified_ for your customization.

## Adding columns

To add columns to the migration, simply use a callback with the `addColumns()` method that receives the table blueprint.

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()
    ->addColumns(function (Blueprint $table) {
        $table->boolean('is_cool')->default(true);
        $table->string('color');
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

You may find yourself needing to alter the type of the morph relation created in the migration. For example, the migration will create an integer-type morph for an ULID-based User model.

To change the morph type, use the `morph...` property access preferably, or the `morph()` method with `integer`, `uuid` or `ulid` if you need to also set an index name (in case your database engine doesn't play nice with large index names).

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()->morphUuid;

return Car::migration()->morph('uuid', 'shorter_morph_index_name');
```

## Custom table name

By default, the models use the standard model name in plural for the table name. If you want to change the table name anything else, set the table using the `$useTable` static property of each Model. You should do this on the `register()` method of your `AppServiceProvider`.

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

## Casts

If you add custom columns, you will find yourself retrieving these columns as a string. To merge additional casts to the model, use the `$useCasts` static property of each of the Models. You should do this on the `register()` method of your `AppServiceProvider`.

```php
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Laragear\Package\Models\Model;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Model::$useCasts = [
            'is_cool' => 'boolean',
            'colors' => AsEncryptedCollection::class,
        ];
    }
}
```

### Configuring the model

All customizable models can be configured with additional fillable, guarded, hidden, visible and appended attributes. These are _merged_ with the original configuration of the model itself, so changes are not destructive. Customize the model using the available static properties:

- `$useCasts`: The casts attributes to merge.
- `$useFillable`: The fillable attributes to merge.
- `$useGuarded`: The guarded attributes to merge.
- `$useHidden`: The hidden attributes to merge.
- `$useVisible`: The visible attributes to merge.
- `$useAppends`: The appends attributes to merge.

```php
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Laragear\Package\Models\Model;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Model::$useCasts = [
            'is_cool' => 'boolean',
            'colors' => AsEncryptedCollection::class,
        ];
    }
}
```
