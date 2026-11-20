<?php

declare(strict_types=1);

use GraystackIT\Pingen\PingenService;
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
 * One registered webhook, as the API describes it.
 *
 * @param  string  $signingKey  The key it was registered with.
 * @return array<string, mixed>
 */
function registeredWebhook(string $id, string $category, string $url, string $signingKey): array
{
    return [
        'id' => $id,
        'type' => 'webhooks',
        'attributes' => ['event_category' => $category, 'url' => $url, 'signing_key' => $signingKey],
    ];
}

/**
 * Bind a Pingen service whose connector answers from the given mock.
 *
 * The command resolves the service out of the container, so the mock has to be
 * put there rather than handed to the command.
 */
function commandPingen(MockClient $mock): void
{
    $service = PingenService::fromConfig();
    $service->connector()->withMockClient($mock);

    app()->instance(PingenService::class, $service);
}

it('lists what is registered', function (): void {
    config()->set('pingen.webhook.signing_key', 'configured-key');

    commandPingen(new MockClient([
        ListWebhooksRequest::class => MockResponse::make([
            'data' => [registeredWebhook('wh-1', 'sent', 'https://app.test/pingen/webhook', 'configured-key')],
        ], 200),
    ]));

    $this->artisan('pingen:webhooks')
        ->expectsOutputToContain('https://app.test/pingen/webhook')
        ->assertExitCode(0);
});

it('says so when a registration cannot be verified with the configured key', function (): void {
    // The failure this command exists to make visible: a webhook registered
    // with another key verifies against nothing, forever, and the provider
    // reports no error of any kind.
    config()->set('pingen.webhook.signing_key', 'configured-key');

    commandPingen(new MockClient([
        ListWebhooksRequest::class => MockResponse::make([
            'data' => [registeredWebhook('wh-1', 'sent', 'https://app.test/pingen/webhook', 'a-different-key')],
        ], 200),
    ]));

    $this->artisan('pingen:webhooks')
        ->expectsOutputToContain('NO')
        ->assertExitCode(0);
});

it('warns when nothing is registered at all', function (): void {
    commandPingen(new MockClient([
        ListWebhooksRequest::class => MockResponse::make(['data' => []], 200),
    ]));

    $this->artisan('pingen:webhooks')
        ->expectsOutputToContain('No webhooks are registered.')
        ->assertExitCode(0);
});

it('replaces the existing registrations when given an endpoint', function (): void {
    config()->set('pingen.webhook.signing_key', 'configured-key');

    $mock = new MockClient([
        ListWebhooksRequest::class => MockResponse::make([
            'data' => [registeredWebhook('wh-old', 'sent', 'https://gone.test/pingen/webhook', 'configured-key')],
        ], 200),
        DeleteWebhookRequest::class => MockResponse::make([], 204),
        CreateWebhookRequest::class => MockResponse::make([
            'data' => registeredWebhook('wh-new', 'sent', 'https://app.test/pingen/webhook', 'configured-key'),
        ], 200),
    ]);

    commandPingen($mock);

    $this->artisan('pingen:webhooks', ['--url' => 'https://app.test/pingen/webhook'])
        ->expectsOutputToContain('removed sent -> https://gone.test/pingen/webhook')
        ->assertExitCode(0);

    // Four categories by default, and the stale one taken away first.
    $mock->assertSentCount(7);
});

it('leaves the existing registrations alone when asked to', function (): void {
    config()->set('pingen.webhook.signing_key', 'configured-key');

    $mock = new MockClient([
        ListWebhooksRequest::class => MockResponse::make(['data' => []], 200),
        CreateWebhookRequest::class => MockResponse::make([
            'data' => registeredWebhook('wh-new', 'issues', 'https://app.test/pingen/webhook', 'configured-key'),
        ], 200),
    ]);

    commandPingen($mock);

    $this->artisan('pingen:webhooks', [
        '--url' => 'https://app.test/pingen/webhook',
        '--category' => ['issues'],
        '--keep' => true,
    ])->assertExitCode(0);

    // One create and the listing afterwards: nothing was deleted, and no
    // listing was needed to find something to delete.
    $mock->assertSentCount(2);
});

it('reports a refusal rather than a stack trace', function (): void {
    commandPingen(new MockClient([
        ListWebhooksRequest::class => MockResponse::make(['errors' => [['detail' => 'Nope']]], 403),
    ]));

    $this->artisan('pingen:webhooks')->assertExitCode(1);
});
