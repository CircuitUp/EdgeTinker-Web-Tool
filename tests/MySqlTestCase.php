<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

/**
 * Base class for tests that need real MySQL (product search uses FULLTEXT
 * indexes, which SQLite does not have).
 *
 * Uses the `edgetinker_test` database. Override with TEST_DB_HOST, TEST_DB_PORT,
 * TEST_DB_DATABASE, TEST_DB_USERNAME and TEST_DB_PASSWORD if yours differs.
 *
 * The schema is migrated once per test run and tables are emptied before each
 * test. Tests are NOT wrapped in a transaction: InnoDB only adds rows to a
 * FULLTEXT index when they are committed, so uncommitted data would be
 * invisible to search.
 */
abstract class MySqlTestCase extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        $config = self::connection();

        try {
            new PDO(
                "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
                $config['username'],
                $config['password'],
                [PDO::ATTR_TIMEOUT => 3],
            );
        } catch (Throwable $e) {
            $this->markTestSkipped("MySQL test database '{$config['database']}' is not reachable: {$e->getMessage()}");
        }

        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh')->assertSuccessful();
            self::$migrated = true;
        } else {
            $this->emptyTables();
        }
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', array_merge(
            $app['config']->get('database.connections.mysql'),
            self::connection(),
        ));

        return $app;
    }

    private function emptyTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            // DELETE rather than TRUNCATE: truncating is slow DDL in MySQL, and test tables are tiny.
            if ($table !== 'migrations') {
                DB::table($table)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    private static function connection(): array
    {
        return [
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_DATABASE') ?: 'edgetinker_test',
            'username' => getenv('TEST_DB_USERNAME') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: '',
        ];
    }
}
