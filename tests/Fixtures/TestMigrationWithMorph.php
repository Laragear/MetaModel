<?php

namespace Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;

class TestMigrationWithMorph extends CustomizableMigration
{
    public function create(Blueprint $table): void
    {
        $table->createCall();

        $this->createMorph($table, 'foo');
        $this->morphCalled = false;
        $this->createNullableMorph($table, 'bar');
    }

    public function addCustomColumns(Blueprint $table): void
    {
        //
    }
}
