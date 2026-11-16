<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
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

        // Each test must start with a fresh in-memory rate limiter. Keep
        // throttle middleware enabled so dedicated 429 tests still exercise
        // the real production limits within their own test.
        if (config('cache.default') === 'array') {
            Cache::store('array')->flush();
        }
    }
}
