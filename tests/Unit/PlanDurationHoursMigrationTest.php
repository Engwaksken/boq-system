<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PlanDurationHoursMigrationTest extends TestCase
{
    private Manager $database;

    private Connection $connection;

    private Migration $migration;

    private mixed $previousFacadeApplication;

    private ?array $previousResolvedInstances;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolvedInstances = (new ReflectionProperty(Facade::class, 'resolvedInstance'))->getValue();

        // No environment configuration, global Capsule, Eloquent, or application boot.
        $container = new Container;
        $this->database = new Manager($container);
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->connection = $this->database->getConnection();
        $container->instance('db.schema', $this->connection->getSchemaBuilder());
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        // A minimal pre-migration schema; no models, factories, or other migrations.
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('duration_days')->nullable();
        });

        $this->migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_03_000001_add_duration_hours_to_plans_table.php';
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->database)) {
                $this->database->getDatabaseManager()->purge();
            }
        } finally {
            Facade::setFacadeApplication($this->previousFacadeApplication);
            (new ReflectionProperty(Facade::class, 'resolvedInstance'))->setValue(null, $this->previousResolvedInstances);
            parent::tearDown();
        }
    }

    public function test_existing_row_receives_null_duration_hours(): void
    {
        $id = $this->connection->table('plans')->insertGetId(['name' => 'Existing', 'duration_days' => 30]);

        $this->migration->up();

        $this->assertSame(
            ['id' => $id, 'name' => 'Existing', 'duration_days' => 30, 'duration_hours' => null],
            (array) $this->connection->table('plans')->find($id),
        );
    }

    #[DataProvider('durationHours')]
    public function test_duration_hours_stores_nullable_zero_and_positive_values(?int $hours): void
    {
        $this->migration->up();
        $id = $this->connection->table('plans')->insertGetId([
            'name' => 'New',
            'duration_days' => null,
            'duration_hours' => $hours,
        ]);

        $this->assertSame($hours, $this->connection->table('plans')->where('id', $id)->value('duration_hours'));
    }

    public static function durationHours(): array
    {
        return ['null' => [null], 'zero' => [0], 'positive' => [48]];
    }

    public function test_rollback_removes_only_duration_hours_and_preserves_existing_values(): void
    {
        $columns = Schema::getColumnListing('plans');
        $id = $this->connection->table('plans')->insertGetId(['name' => 'Existing', 'duration_days' => 30]);
        $this->migration->up();
        $this->connection->table('plans')->where('id', $id)->update(['duration_hours' => 48]);

        $this->migration->down();

        $this->assertSame($columns, Schema::getColumnListing('plans'));
        $this->assertSame(
            ['id' => $id, 'name' => 'Existing', 'duration_days' => 30],
            (array) $this->connection->table('plans')->find($id),
        );
    }

    public function test_migration_can_be_reapplied_after_rollback(): void
    {
        $id = $this->connection->table('plans')->insertGetId(['name' => 'Existing', 'duration_days' => 30]);
        $this->migration->up();
        $this->migration->down();

        $this->migration->up();

        $this->assertNull($this->connection->table('plans')->where('id', $id)->value('duration_hours'));
        $this->connection->table('plans')->where('id', $id)->update(['duration_hours' => 48]);
        $this->assertSame(48, $this->connection->table('plans')->where('id', $id)->value('duration_hours'));
    }
}
