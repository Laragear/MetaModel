<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laragear\MetaModel\HasCustomization;

class TestCustomizableModel extends Model
{
    use HasCustomization;
}
