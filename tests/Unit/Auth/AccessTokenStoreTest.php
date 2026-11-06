<?php

declare(strict_types=1);

use GraystackIT\Pingen\Auth\AccessTokenStore;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Support\Environment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

it('fetches a token with the client_credentials grant', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);

    expect(AccessTokenStore::fromConfig()->token())->toBe('fresh-token');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://identity-staging.pingen.com/auth/access-tokens'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'test-client-id';
    });
});

it('serves the cached token without asking again', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);

    $store = AccessTokenStore::fromConfig();

    $store->token();
    $store->token();
    $store->token();

    Http::assertSentCount(1);
});

it('fetches again after the token is forgotten', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);

    $store = AccessTokenStore::fromConfig();

    $store->token();
    $store->forget();
    $store->token();

    Http::assertSentCount(2);
});

it('keeps staging and production tokens apart', function (): void {
    // The same client id against two hosts. Without the host in the cache key
    // the second call would be served the first token, and the request would
    // go to production authenticated for staging.
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'staging-token', 'expires_in' => 3600]),
        'identity.pingen.com/*' => Http::response(['access_token' => 'production-token', 'expires_in' => 3600]),
    ]);

    $staging = new AccessTokenStore(new Environment(staging: true), 'client', 'secret');
    $production = new AccessTokenStore(new Environment(staging: false), 'client', 'secret');

    expect($staging->token())->toBe('staging-token')
        ->and($production->token())->toBe('production-token');
});

it('keeps two clients on one host apart', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::sequence()
            ->push(['access_token' => 'token-for-a', 'expires_in' => 3600])
            ->push(['access_token' => 'token-for-b', 'expires_in' => 3600]),
    ]);

    $a = new AccessTokenStore(new Environment(staging: true), 'client-a', 'secret');
    $b = new AccessTokenStore(new Environment(staging: true), 'client-b', 'secret');

    expect($a->token())->toBe('token-for-a')
        ->and($b->token())->toBe('token-for-b');
});

it('subtracts the margin from the stated lifetime', function (): void {
    config()->set('pingen.token_cache.margin', 300);

    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);

    AccessTokenStore::fromConfig()->token();

    // Travelling past the shortened lifetime must produce a second fetch.
    $this->travel(3400)->seconds();

    AccessTokenStore::fromConfig()->token();

    Http::assertSentCount(2);
});

it('caches for at least a minute when the response names no lifetime', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'fresh-token']),
    ]);

    $store = AccessTokenStore::fromConfig();

    $store->token();
    $store->token();

    Http::assertSentCount(1);
});

it('sends the configured scopes', function (): void {
    config()->set('pingen.scopes', 'letter batch webhook');

    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);

    AccessTokenStore::fromConfig()->token();

    Http::assertSent(fn ($request): bool => $request['scope'] === 'letter batch webhook');
});

it('refuses to work without an OAuth client', function (): void {
    config()->set('pingen.client_id', null);

    AccessTokenStore::fromConfig()->token();
})->throws(ConfigurationException::class, 'PINGEN_CLIENT_ID');

it('reports a refused credential rather than caching nothing', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['errors' => []], 401),
    ]);

    AccessTokenStore::fromConfig()->token();
})->throws(PingenException::class);

it('reports a token response with no token in it', function (): void {
    Http::fake([
        'identity-staging.pingen.com/*' => Http::response(['expires_in' => 3600]),
    ]);

    AccessTokenStore::fromConfig()->token();
})->throws(PingenException::class, 'no access_token');
