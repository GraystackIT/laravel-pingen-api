<?php

declare(strict_types=1);

use GraystackIT\Pingen\Data\LetterEvent;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\PingenService;
use GraystackIT\Pingen\Requests\LetterEvents\ListLetterEventsRequest;
use GraystackIT\Pingen\Requests\Organisations\ListOrganisationsRequest;
use GraystackIT\Pingen\Requests\Webhooks\CreateWebhookRequest;
use GraystackIT\Pingen\Requests\Webhooks\DeleteWebhookRequest;
use GraystackIT\Pingen\Requests\Webhooks\ListWebhooksRequest;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
    ]);
});

/**
 * A service whose API connector answers from the given mock.
 */
function apiService(MockClient $mock): PingenService
{
    $service = PingenService::fromConfig();
    $service->connector()->withMockClient($mock);

    return $service;
}

it('registers a webhook with the configured signing key', function (): void {
    config()->set('pingen.webhook.signing_key', 'configured-key');

    $mock = new MockClient([
        CreateWebhookRequest::class => MockResponse::make([
            'data' => [
                'id' => 'wh-1',
                'type' => 'webhooks',
                'attributes' => ['event_category' => 'letters', 'url' => 'https://app.test/pingen/webhook'],
            ],
        ], 200),
    ]);

    $webhook = apiService($mock)->webhooks()->create('letters', 'https://app.test/pingen/webhook');

    $body = json_decode((string) $mock->getLastPendingRequest()->body(), true);

    expect($webhook->id)->toBe('wh-1')
        ->and($body['data']['attributes']['signing_key'])->toBe('configured-key');
});

it('refuses to register a webhook with no signing key', function (): void {
    // Pingen never returns the key again, so registering without one leaves an
    // endpoint that can never verify anything it receives.
    config()->set('pingen.webhook.signing_key', null);

    apiService(new MockClient([]))->webhooks()->create('letters', 'https://app.test/hook');
})->throws(PingenException::class, 'signing key');

it('lists webhooks', function (): void {
    $mock = new MockClient([
        ListWebhooksRequest::class => MockResponse::make([
            'data' => [
                ['id' => 'wh-1', 'type' => 'webhooks', 'attributes' => ['event_category' => 'letters', 'url' => 'https://a.test']],
                ['id' => 'wh-2', 'type' => 'webhooks', 'attributes' => ['event_category' => 'letters', 'url' => 'https://b.test']],
            ],
        ], 200),
    ]);

    expect(apiService($mock)->webhooks()->list())->toHaveCount(2);
});

it('deletes a webhook', function (): void {
    $mock = new MockClient([DeleteWebhookRequest::class => MockResponse::make([], 204)]);

    apiService($mock)->webhooks()->delete('wh-1');

    expect($mock->getLastPendingRequest()->getUrl())->toContain('/webhooks/wh-1');
});

it('reads a letter event history', function (): void {
    $mock = new MockClient([
        ListLetterEventsRequest::class => MockResponse::make([
            'data' => [[
                'id' => 'ev-1',
                'type' => 'letter_events',
                'attributes' => [
                    'code' => 'delivered',
                    'name' => 'Delivered',
                    'producer' => 'Post',
                    'location' => 'Example City',
                    'has_image' => false,
                    'data' => [],
                    'emitted_at' => '2026-11-06T08:00:00+00:00',
                    'created_at' => '2026-11-06T08:05:00+00:00',
                ],
            ]],
        ], 200),
    ]);

    $events = apiService($mock)->events()->list('letter-1');

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(LetterEvent::class)
        ->and($events[0]->code)->toBe('delivered')
        ->and($events[0]->emittedAt?->toTimeString())->toBe('08:00:00');
});

it('lists the organisations the client can reach', function (): void {
    $mock = new MockClient([
        ListOrganisationsRequest::class => MockResponse::make([
            'data' => [[
                'id' => 'org-9',
                'type' => 'organisations',
                'attributes' => ['name' => 'Example GmbH', 'status' => 'active'],
            ]],
        ], 200),
    ]);

    $organisations = apiService($mock)->organisations()->list();

    expect($organisations)->toHaveCount(1)
        ->and($organisations[0]->id)->toBe('org-9')
        ->and($organisations[0]->name)->toBe('Example GmbH');

    // Not organisation-scoped: the path must carry no organisation at all.
    expect($mock->getLastPendingRequest()->getUrl())
        ->toBe('https://api-staging.pingen.com/organisations');
});
