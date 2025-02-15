<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\TestCustomizableModel as TestModel;

class HasCustomizationTest extends TestCase
{
    protected Container $container;
    protected MockInterface $schema;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();

        SchemaFacade::setFacadeApplication($this->container);

        TestModel::customize(null);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function test_customization(): void
    {
        TestModel::customize(function (TestModel $model) {
            $model->setTable('test_table');
        });

        static::assertSame('test_table', (new TestModel())->getTable());
    }

    public function test_migration_is_not_implemented_by_default(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The '.TestModel::class.' has not implemented customizable migrations.');

        TestModel::migration();
    }
}
