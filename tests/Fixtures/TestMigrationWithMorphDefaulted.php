<?php

namespace Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Laragear\MetaModel\CustomizableMigration;

class TestMigrationWithMorphDefaulted extends CustomizableMigration
{
    public function create(Blueprint $table): void
    {
        $table->createCall();

        $this->createMorph($table, 'foo', 'custom_index');
        $this->morphCalled = false;
        $this->createNullableMorph($table, 'bar', 'custom_index');
    }

    public function addCustomColumns(Blueprint $table): void
    {
        //
    }
}
