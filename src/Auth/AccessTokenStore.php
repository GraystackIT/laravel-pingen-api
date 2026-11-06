<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Auth;

use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Support\Environment;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches and caches the OAuth access token every API request is made with.
 *
 * Pingen issues tokens through the `client_credentials` grant, which means the
 * token belongs to the application rather than to a person and can therefore
 * be shared by every request the application makes. It is cached until shortly
 * before it expires.
 *
 * The cache key carries a hash of the client id and the identity host. Two
 * things depend on that: a deployment that talks to staging and production in
 * one process never hands a staging token to the production API, and an
 * application holding two OAuth clients never hands one client the other
 * token. Both failures are invisible — the request simply comes back 401 or,
 * worse, succeeds against the wrong account.
 *
 * This deliberately uses Laravel's HTTP client rather than Saloon: the token
 * endpoint is the one call that cannot go through the connector, because the
 * connector needs the token to build its headers.
 */
class AccessTokenStore
{
    /**
     * @param  Environment  $environment  Which identity host to ask.
     * @param  string|null  $clientId  OAuth client id; null falls back to configuration.
     * @param  string|null  $clientSecret  OAuth client secret; null falls back to configuration.
     * @param  string|null  $scopes  Space-separated scopes, or null to request none explicitly.
     */
    public function __construct(
        private readonly Environment $environment,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly ?string $scopes = null,
    ) {}

    /**
     * Build the store from the package configuration.
     */
    public static function fromConfig(): self
    {
        return new self(
            environment: Environment::fromConfig(),
            clientId: self::stringOrNull(config('pingen.client_id')),
            clientSecret: self::stringOrNull(config('pingen.client_secret')),
            scopes: self::stringOrNull(config('pingen.scopes')),
        );
    }

    /**
     * A valid access token, from the cache when one is still good.
     *
     * @throws ConfigurationException When the OAuth client is not configured.
     * @throws PingenException When the identity server refuses the credentials.
     */
    public function token(): string
    {
        $cached = $this->cache()->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetch();
    }

    /**
     * Throw the cached token away, so the next call fetches a fresh one.
     *
     * Called when the API answers 401 with a token we believed was valid —
     * a revoked client, a rotated secret, or an expiry we calculated wrong.
     */
    public function forget(): void
    {
        $this->cache()->forget($this->cacheKey());
    }

    /**
     * Ask the identity server for a new token and cache it.
     *
     * @throws ConfigurationException When the OAuth client is not configured.
     * @throws PingenException When the identity server refuses the credentials.
     */
    public function fetch(): string
    {
        $clientId = $this->clientId;
        $clientSecret = $this->clientSecret;

        if ($clientId === null || $clientSecret === null) {
            throw new ConfigurationException(
                'The Pingen OAuth client is not configured. Set PINGEN_CLIENT_ID and PINGEN_CLIENT_SECRET.'
            );
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        if ($this->scopes !== null) {
            $payload['scope'] = $this->scopes;
        }

        $response = Http::asJson()
            ->acceptJson()
            ->post($this->environment->identityUrl().'/auth/access-tokens', $payload);

        if ($response->failed()) {
            throw new PingenException(
                'Pingen refused the OAuth client credentials (status '.$response->status().').',
                $response->status(),
            );
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new PingenException('The Pingen token response carried no access_token.');
        }

        $this->cache()->put($this->cacheKey(), $token, $this->lifetime($body));

        return $token;
    }

    /**
     * How many seconds the token may be cached for.
     *
     * The margin is subtracted so a request that starts while the token is
     * still valid cannot arrive after it expired. A response without
     * `expires_in`, or one so short-lived that the margin eats all of it, is
     * cached for a single minute rather than not at all — repeating the token
     * call on every API request would be its own problem.
     *
     * @param  array<string, mixed>  $body  The decoded token response.
     */
    private function lifetime(array $body): int
    {
        $expiresIn = (int) ($body['expires_in'] ?? 0);
        $margin = max(0, (int) config('pingen.token_cache.margin', 60));

        return max(60, $expiresIn - $margin);
    }

    /**
     * The cache key, bound to this client and this identity host.
     */
    private function cacheKey(): string
    {
        $prefix = (string) config('pingen.token_cache.key', 'pingen:access-token');
        $scope = hash('sha256', ($this->clientId ?? '').'|'.$this->environment->identityUrl().'|'.($this->scopes ?? ''));

        return $prefix.':'.substr($scope, 0, 16);
    }

    /**
     * The cache store to use.
     */
    private function cache(): CacheRepository
    {
        $store = config('pingen.token_cache.store');

        return is_string($store) && $store !== '' ? Cache::store($store) : Cache::store();
    }

    /**
     * Read a configuration value as a non-empty string, or null.
     *
     * @param  mixed  $value  The raw configuration value.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
