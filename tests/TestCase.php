<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles and permissions are seeded once per test run (RefreshDatabase
     * then wraps each test in a transaction).
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    /**
     * Safety net: RefreshDatabase wipes the database. Tests only ever run on
     * the in-memory SQLite database of phpunit.xml. If the configuration is
     * cached (php artisan optimize / config:cache), phpunit.xml is ignored
     * and the real database would be wiped: refuse to start instead.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($app->configurationIsCached() || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Tests arrêtés : ils doivent tourner sur SQLite en mémoire, pas sur « {$connection} / {$database} ». "
                .'Lancez « php artisan config:clear » puis relancez les tests.'
            );
        }

        return $app;
    }
}
