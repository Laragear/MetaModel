<?php

namespace Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;

class TestMigration extends CustomizableMigration
{
    public static bool $callMethod = false;

    public function create(Blueprint $table): void
    {
        $table->createCall();

        if (static::$callMethod) {
            $this->addColumns($table);
        }
    }
}
