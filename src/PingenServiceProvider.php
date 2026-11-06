<?php

declare(strict_types=1);

namespace GraystackIT\Pingen;

use GraystackIT\Pingen\Auth\AccessTokenStore;
use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Connectors\UploadConnector;
use GraystackIT\Pingen\Console\PruneWebhookEventsCommand;
use GraystackIT\Pingen\Services\FileUploadService;
use GraystackIT\Pingen\Services\LetterEventService;
use GraystackIT\Pingen\Services\LetterService;
use GraystackIT\Pingen\Services\OrganisationService;
use GraystackIT\Pingen\Services\WebhookService;
use GraystackIT\Pingen\Support\Environment;
use GraystackIT\Pingen\Support\LetterRecorder;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the package with the application.
 *
 * The bindings resolve the *configured* organisation. An application holding
 * several Pingen accounts does not rebind anything — it asks the resolved
 * service for another with `forOrganisation()`, which returns a separate
 * instance. That is why nothing here is a true singleton of an organisation's
 * state.
 */
class PingenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pingen.php', 'pingen');

        $this->app->singleton(Environment::class, static fn (): Environment => Environment::fromConfig());

        $this->app->singleton(AccessTokenStore::class, static fn (): AccessTokenStore => AccessTokenStore::fromConfig());

        $this->app->singleton(UploadConnector::class, static fn (): UploadConnector => new UploadConnector);

        $this->app->singleton(LetterRecorder::class, static fn (): LetterRecorder => new LetterRecorder);

        $this->app->bind(PingenConnector::class, fn (): PingenConnector => new PingenConnector(
            $this->app->make(AccessTokenStore::class),
            (string) config('pingen.organisation_id', ''),
            $this->app->make(Environment::class),
        ));

        $this->app->bind(FileUploadService::class, fn (): FileUploadService => new FileUploadService(
            $this->app->make(PingenConnector::class),
            $this->app->make(UploadConnector::class),
        ));

        $this->app->bind(LetterService::class, fn (): LetterService => new LetterService(
            $this->app->make(PingenConnector::class),
            $this->app->make(FileUploadService::class),
            $this->app->make(LetterRecorder::class),
        ));

        $this->app->bind(
            LetterEventService::class,
            fn (): LetterEventService => new LetterEventService($this->app->make(PingenConnector::class)),
        );

        $this->app->bind(
            WebhookService::class,
            fn (): WebhookService => new WebhookService($this->app->make(PingenConnector::class)),
        );

        $this->app->bind(
            OrganisationService::class,
            fn (): OrganisationService => new OrganisationService($this->app->make(PingenConnector::class)),
        );

        $this->app->bind(PingenService::class, fn (): PingenService => new PingenService(
            $this->app->make(PingenConnector::class),
            $this->app->make(LetterRecorder::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PruneWebhookEventsCommand::class]);

            $this->publishes([
                __DIR__.'/../config/pingen.php' => config_path('pingen.php'),
            ], 'pingen-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'pingen-migrations');
        }

        // Optional, so an application that only wants the API client inherits
        // no tables. The webhook replay guard is one of them — see the config.
        if (config('pingen.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Optional too: an application may prefer to route deliveries itself
        // and point its own route at the controller.
        if (config('pingen.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }
    }
}
