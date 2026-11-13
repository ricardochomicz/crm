<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never allow database-refreshing feature tests to target a persistent database.
        // Check the resolved configuration, not just phpunit.xml or .env.testing.
        $default = config('database.default');
        $connection = config("database.connections.{$default}");

        if ($default !== 'sqlite'
            || ! is_array($connection)
            || ($connection['database'] ?? null) !== ':memory:'
            || filled($connection['url'] ?? null)) {
            throw new RuntimeException(
                'Testes bloqueados: a conexão de banco deve ser sqlite em memória, sem DB_URL. '
                . 'Confira a configuração efetiva do ambiente de testes.'
            );
        }
    }
}
