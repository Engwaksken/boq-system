<?php

namespace Tests\Unit;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Unsaved fixtures only: no application boot, schema, migrations, or queries. */
abstract class IsolatedPlanTestCase extends TestCase
{
    private Container $previousContainer;

    private mixed $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->previousResolver = Model::getConnectionResolver();

        $container = new Container;
        $container->instance('config', new Repository);
        $container->instance(Generator::class, FakerFactory::create('en_US'));
        Container::setInstance($container);

        $database = new Manager($container);
        $database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Model::setConnectionResolver($database->getDatabaseManager());

        $connection = $database->getConnection();
        $this->assertSame('sqlite', $connection->getConfig('driver'));
        $this->assertSame(':memory:', $connection->getDatabaseName());
        $this->assertEmpty($connection->getConfig('url'));
        $connection->beforeExecuting(static function (): void {
            throw new RuntimeException('These isolated tests must never execute database queries.');
        });
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();
        if ($this->previousResolver !== null) {
            Model::setConnectionResolver($this->previousResolver);
        }
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }
}
