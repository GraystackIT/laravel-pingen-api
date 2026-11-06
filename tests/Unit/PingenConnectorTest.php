<?php

declare(strict_types=1);

use GraystackIT\Pingen\Auth\AccessTokenStore;
use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Connectors\UploadConnector;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Requests\FileUpload\RequestFileUploadRequest;
use GraystackIT\Pingen\Requests\FileUpload\UploadFileRequest;
use GraystackIT\Pingen\Requests\Letters\GetLetterRequest;
use GraystackIT\Pingen\Support\Environment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Cache::flush();
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'identity.pingen.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
    ]);
});

/**
 * Build a connector for the given environment.
 */
function connector(bool $staging = true, string $organisationId = 'org-1'): PingenConnector
{
    return new PingenConnector(
        AccessTokenStore::fromConfig(),
        $organisationId,
        new Environment(staging: $staging),
    );
}

it('points at the staging host when staging is on', function (): void {
    expect(connector(staging: true)->resolveBaseUrl())->toBe('https://api-staging.pingen.com');
});

it('points at the production host otherwise', function (): void {
    expect(connector(staging: false)->resolveBaseUrl())->toBe('https://api.pingen.com');
});

it('keeps the organisation out of the base url', function (): void {
    // /file-upload is the one endpoint that is not organisation-scoped, so
    // baking the organisation into the base url would break exactly it.
    expect(connector()->resolveBaseUrl())->not->toContain('organisations');
});

it('refuses to be built without an organisation', function (): void {
    connector(organisationId: '   ');
})->throws(ConfigurationException::class, 'PINGEN_ORGANISATION_ID');

it('sends the bearer token and the JSON:API content type', function (): void {
    $mock = new MockClient([
        GetLetterRequest::class => MockResponse::make(letterResponse(), 200),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);
    $connector->send(new GetLetterRequest('org-1', 'letter-1'));

    $sent = $mock->getLastPendingRequest();

    expect($sent->headers()->get('Authorization'))->toBe('Bearer test-token')
        ->and($sent->headers()->get('Accept'))->toBe('application/vnd.api+json')
        ->and($sent->headers()->get('Content-Type'))->toBe('application/vnd.api+json');
});

it('builds the organisation-scoped letter url', function (): void {
    $mock = new MockClient([
        GetLetterRequest::class => MockResponse::make(letterResponse(), 200),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);
    $connector->send(new GetLetterRequest('org-1', 'letter-1'));

    expect($mock->getLastPendingRequest()->getUrl())
        ->toBe('https://api-staging.pingen.com/organisations/org-1/deliveries/letters/letter-1');
});

it('asks for an upload slot without an organisation in the path', function (): void {
    $mock = new MockClient([
        RequestFileUploadRequest::class => MockResponse::make(fileUploadResponse(), 200),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);
    $connector->send(new RequestFileUploadRequest);

    expect($mock->getLastPendingRequest()->getUrl())
        ->toBe('https://api-staging.pingen.com/file-upload');
});

it('never sends credentials to the upload host', function (): void {
    // The signed URL points at object storage. A bearer token sent there is a
    // credential leak that nothing in the response would reveal.
    $mock = new MockClient([
        UploadFileRequest::class => MockResponse::make('', 200),
    ]);

    $uploads = new UploadConnector;
    $uploads->withMockClient($mock);
    $uploads->send(new UploadFileRequest('https://storage.example.test/upload/abc', 'PDF-BYTES'));

    $sent = $mock->getLastPendingRequest();

    expect($sent->headers()->get('Authorization'))->toBeNull()
        ->and($sent->headers()->get('Content-Type'))->toBe('application/pdf')
        ->and((string) $sent->body())->toBe('PDF-BYTES');
});

it('refreshes the token once and retries when the API answers 401', function (): void {
    Cache::flush();

    Http::fake([
        'identity-staging.pingen.com/*' => Http::sequence()
            ->push(['access_token' => 'stale-token', 'expires_in' => 3600])
            ->push(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);

    $mock = new MockClient([
        GetLetterRequest::class => MockResponse::make(['errors' => []], 401),
    ]);

    $connector = connector();
    $connector->withMockClient($mock);

    try {
        $connector->send(new GetLetterRequest('org-1', 'letter-1'));
    } catch (Throwable) {
        // The second 401 is still a failure; what matters is what happened in between.
    }

    // Two token calls: the first to authenticate, the second after the 401
    // forced the cached token to be thrown away.
    Http::assertSentCount(2);
});
