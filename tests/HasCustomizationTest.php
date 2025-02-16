<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Laragear\MetaModel\HasCustomization;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HasCustomizationTest extends TestCase
{
    protected Container $container;
    protected MockInterface $schema;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();

        SchemaFacade::setFacadeApplication($this->container);

        TestCustomizableModel::customize(null);
    }

    protected function tearDown(): void
    {
        m::close();
        Container::setInstance();
    }

    public function test_customization(): void
    {
        TestCustomizableModel::customize(function (TestCustomizableModel $model) {
            $model->setTable('test_table');
        });

        static::assertSame('test_table', (new TestCustomizableModel())->getTable());
    }

    public function test_migration_is_not_implemented_by_default(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The '.TestCustomizableModel::class.' has not implemented customizable migrations.');

        TestCustomizableModel::migration();
    }
}

class TestCustomizableModel extends Model
{
    use HasCustomization;
}
