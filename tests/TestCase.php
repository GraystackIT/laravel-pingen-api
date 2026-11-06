<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Tests;

use GraystackIT\Pingen\PingenServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Base test case: boots the package inside a throwaway Laravel application.
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register the package service provider for all tests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PingenServiceProvider::class];
    }

    /**
     * Define the environment every test runs in.
     *
     * The credentials are invented and the token is pre-seeded into the cache,
     * so no test has to mock the identity server just to reach the API — the
     * ones that care about the token lifecycle clear it themselves.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('pingen.client_id', 'test-client-id');
        $app['config']->set('pingen.client_secret', 'test-client-secret');
        $app['config']->set('pingen.organisation_id', '00000000-0000-4000-8000-000000000001');
        $app['config']->set('pingen.staging', true);
        $app['config']->set('pingen.webhook.signing_key', null);
        $app['config']->set('pingen.store_logs', false);
        $app['config']->set('pingen.storage_driver', 'log');
        $app['config']->set('pingen.capture_path', null);
        $app['config']->set('pingen.retry.times', 1);
        $app['config']->set('pingen.retry.delay', 0);
        $app['config']->set('pingen.defaults.delivery_product', 'cheap');
        $app['config']->set('pingen.defaults.print_mode', 'simplex');
        $app['config']->set('pingen.defaults.print_spectrum', 'grayscale');
    }

    /**
     * The organisation id every test sends against.
     */
    protected function organisationId(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }
}
