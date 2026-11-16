<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

class PostgresTestDatabase
{
    public static function configure(): void
    {
        if (getenv('PG_TEST_DATABASE') !== 'infraticket_test' || ! getenv('PG_TEST_HOST')) {
            throw new \RuntimeException('Se requiere la base aislada infraticket_test y PG_TEST_HOST.');
        }

        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'pgsql', 'host' => getenv('PG_TEST_HOST'), 'port' => 5432,
            'database' => 'infraticket_test', 'username' => getenv('PG_TEST_USERNAME'),
            'password' => getenv('PG_TEST_PASSWORD'), 'charset' => 'utf8', 'prefix' => '',
            'search_path' => 'public', 'sslmode' => 'prefer',
        ]]);
        DB::purge('pgsql');
    }
}
