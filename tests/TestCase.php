<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        // Run BEFORE RefreshDatabase/DatabaseMigrations can drop any tables.
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        $testDatabase = is_string($database)
            && (str_ends_with($database, '_test') || str_ends_with($database, '_testing'));

        if ($connection !== 'mysql' || ! $testDatabase || config("database.connections.{$connection}.url")) {
            throw new RuntimeException(
                'Feature tests require a dedicated MySQL database ending in _test or _testing. '
                .'Use discrete DB_* settings, not DB_URL. Copy .env.testing.example to .env.testing and configure its separate test credentials. '
                .'No migrations have been started by this test.'
            );
        }

        return parent::setUpTraits();
    }
}
