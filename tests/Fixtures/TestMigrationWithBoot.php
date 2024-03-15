<?php

namespace Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\MetaModel\CustomizableMigration;

class TestMigrationWithBoot extends CustomizableMigration
{
    public static bool $callMethod = false;

    protected function boot(): void
    {
        Schema::create('test', fn () => true);
    }

    public function create(Blueprint $table): void
    {
        $table->createCall();

        if (static::$callMethod) {
            $this->addColumns($table);
        }
    }
}
