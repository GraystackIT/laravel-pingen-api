<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Services;

use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Data\Webhook;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Requests\Webhooks\CreateWebhookRequest;
use GraystackIT\Pingen\Requests\Webhooks\DeleteWebhookRequest;
use GraystackIT\Pingen\Requests\Webhooks\GetWebhookRequest;
use GraystackIT\Pingen\Requests\Webhooks\ListWebhooksRequest;
use GraystackIT\Pingen\Services\Concerns\MapsPingenErrors;
use Saloon\Exceptions\Request\RequestException;

/**
 * Registers and removes webhooks.
 *
 * Worth saying once: the signing key is chosen by the caller and never
 * returned again. Whatever is passed to {@see create()} must already be stored
 * where the receiving endpoint can read it, or the first delivery arrives and
 * cannot be verified.
 */
class WebhookService
{
    use MapsPingenErrors;

    /**
     * @param  PingenConnector  $connector  Connector bound to the organisation.
     */
    public function __construct(private readonly PingenConnector $connector) {}

    /**
     * Register a webhook.
     *
     * @param  string  $eventCategory  Which events to receive.
     * @param  string  $url  Publicly reachable endpoint.
     * @param  string|null  $signingKey  Secret to sign with; defaults to the configured one.
     *
     * @throws PingenException When the API refuses, or no signing key is available.
     */
    public function create(string $eventCategory, string $url, ?string $signingKey = null): Webhook
    {
        $signingKey ??= (string) config('pingen.webhook.signing_key', '');

        if (trim($signingKey) === '') {
            throw new PingenException(
                'A webhook needs a signing key. Pass one, or set PINGEN_WEBHOOK_SIGNING_KEY.'
            );
        }

        try {
            $response = $this->connector->send(new CreateWebhookRequest(
                organisationId: $this->connector->organisationId(),
                eventCategory: $eventCategory,
                url: $url,
                signingKey: $signingKey,
            ));
        } catch (RequestException $exception) {
            throw $this->mapException($exception, rejectable: true);
        }

        return Webhook::fromResponse((array) $response->json());
    }

    /**
     * List the registered webhooks.
     *
     * @return array<int, Webhook>
     *
     * @throws PingenException When the API refuses.
     */
    public function list(): array
    {
        try {
            $response = $this->connector->send(
                new ListWebhooksRequest($this->connector->organisationId())
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        return Webhook::collectionFromResponse((array) $response->json());
    }

    /**
     * Read one webhook.
     *
     * @param  string  $webhookId  UUID of the webhook.
     *
     * @throws PingenException When the API refuses.
     */
    public function get(string $webhookId): Webhook
    {
        try {
            $response = $this->connector->send(
                new GetWebhookRequest($this->connector->organisationId(), $webhookId)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        return Webhook::fromResponse((array) $response->json());
    }

    /**
     * Remove a webhook.
     *
     * @param  string  $webhookId  UUID of the webhook.
     *
     * @throws PingenException When the API refuses.
     */
    public function delete(string $webhookId): void
    {
        try {
            $this->connector->send(
                new DeleteWebhookRequest($this->connector->organisationId(), $webhookId)
            );
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }
    }
}
