<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Never inherit the office/provider settings in the developer's .env.
        // A separate optional .env.testing may contain synthetic test settings.
        $app->loadEnvironmentFrom(is_file(__DIR__.'/../.env.testing') ? '.env.testing' : '.env.testing.example');

        $app->make(Kernel::class)->bootstrap();

        // Isolated fixtures must not need a real application encryption key.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Older transactional integration suites used to need an imported SQL
        // dump. Build their disposable schema from the same install migrations.
        // Never apply this setup to a persistent or remotely configured database.
        if (in_array(DatabaseTransactions::class, class_uses_recursive(static::class), true)) {
            $connection = $app['db']->connection();
            if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
                throw new \RuntimeException('Transactional integration tests require isolated in-memory SQLite.');
            }
            $app->make(Kernel::class)->call('migrate', ['--force' => true]);
            // Production report queries use MySQL MONTH(). Match the existing
            // reporting fixtures' SQLite shim without changing production SQL.
            $connection->getPdo()->sqliteCreateFunction('MONTH', static fn (?string $date): ?int => $date === null || $date === '' ? null : (int) substr($date, 5, 2), 1);
            $connection->getPdo()->sqliteCreateFunction('YEAR', static fn (?string $date): ?int => $date === null || $date === '' ? null : (int) substr($date, 0, 4), 1);
            $connection->getPdo()->sqliteCreateFunction('FIELD', static function ($needle, ...$values): int {
                $position = array_search($needle, $values, true);

                return $position === false ? 0 : $position + 1;
            }, -1);
        }

        return $app;
    }
}
