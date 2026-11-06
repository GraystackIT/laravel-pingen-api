<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Support;

/**
 * Resolves which pair of Pingen hosts a configuration points at.
 *
 * Pingen runs production and staging as two separate worlds: different
 * identity server, different API host, different accounts, different data. The
 * two hosts therefore always move together, which is why `staging` is one flag
 * rather than two URLs — and why the token cache is keyed by the host as well
 * as the client (see {@see \GraystackIT\Pingen\Auth\AccessTokenStore}).
 */
final class Environment
{
    public const API_PRODUCTION = 'https://api.pingen.com';

    public const API_STAGING = 'https://api-staging.pingen.com';

    public const IDENTITY_PRODUCTION = 'https://identity.pingen.com';

    public const IDENTITY_STAGING = 'https://identity-staging.pingen.com';

    /**
     * @param  bool  $staging  Whether the staging environment is in use.
     * @param  string|null  $apiUrl  Explicit API host, overriding the environment.
     * @param  string|null  $identityUrl  Explicit identity host, overriding the environment.
     */
    public function __construct(
        private readonly bool $staging = false,
        private readonly ?string $apiUrl = null,
        private readonly ?string $identityUrl = null,
    ) {}

    /**
     * Build the environment from the package configuration.
     */
    public static function fromConfig(): self
    {
        return new self(
            staging: (bool) config('pingen.staging', false),
            apiUrl: self::nullIfBlank(config('pingen.base_url')),
            identityUrl: self::nullIfBlank(config('pingen.identity_url')),
        );
    }

    /**
     * The API host, without a trailing slash.
     */
    public function apiUrl(): string
    {
        return rtrim($this->apiUrl ?? ($this->staging ? self::API_STAGING : self::API_PRODUCTION), '/');
    }

    /**
     * The identity host that issues access tokens, without a trailing slash.
     */
    public function identityUrl(): string
    {
        return rtrim($this->identityUrl ?? ($this->staging ? self::IDENTITY_STAGING : self::IDENTITY_PRODUCTION), '/');
    }

    /**
     * Whether the staging environment is in use.
     */
    public function isStaging(): bool
    {
        return $this->staging;
    }

    /**
     * Treat an empty or whitespace-only configuration value as absent.
     *
     * @param  mixed  $value  The raw configuration value.
     */
    private static function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
