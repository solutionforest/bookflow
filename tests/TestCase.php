<?php

namespace SolutionForest\Bookflow\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use SolutionForest\Bookflow\BookflowServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'SolutionForest\\Bookflow\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Refresh migrations to ensure clean database state
        $this->artisan('migrate:fresh');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Create test tables for resources and customers
        $this->createTestTables();
    }

    protected function getPackageProviders($app)
    {
        return [
            BookflowServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function createTestTables(): void
    {
        // Create tables for test models (resources and customers)
        $this->app['db']->connection()->getSchemaBuilder()->create('resources', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('customers', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }
}
