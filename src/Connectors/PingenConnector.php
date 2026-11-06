<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Connectors;

use GraystackIT\Pingen\Auth\AccessTokenStore;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Support\Environment;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Faking\MockClient;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Throwable;

/**
 * Talks to the Pingen API on behalf of one organisation.
 *
 * Two things are worth knowing about this class.
 *
 * The organisation is a constructor argument rather than a configuration
 * lookup. Every Pingen endpoint is scoped to one, and an application may hold
 * several accounts — so the connector is cheap to build per organisation
 * instead of being a singleton that can only ever serve the configured one.
 *
 * The access token is set in {@see boot()}, not in {@see defaultHeaders()}.
 * Saloon memoises a connector's default headers on first use, so a token put
 * there is baked into the instance: forgetting the cached token and retrying
 * would resend the dead one, and the 401 retry below would be a no-op that
 * looks like it works. `boot()` runs for every pending request, which is what
 * makes a refreshed token actually reach the wire.
 */
class PingenConnector extends Connector
{
    use AlwaysThrowOnErrors;

    /**
     * @param  AccessTokenStore  $tokens  Supplies and refreshes the bearer token.
     * @param  string  $organisationId  UUID of the organisation requests are scoped to.
     * @param  Environment  $environment  Which API host to talk to.
     *
     * @throws ConfigurationException When no organisation is named.
     */
    public function __construct(
        private readonly AccessTokenStore $tokens,
        private readonly string $organisationId,
        private readonly Environment $environment,
    ) {
        if (trim($this->organisationId) === '') {
            throw new ConfigurationException(
                'No Pingen organisation was given. Set PINGEN_ORGANISATION_ID, or pass one to forOrganisation().'
            );
        }
    }

    /**
     * The organisation this connector is bound to.
     */
    public function organisationId(): string
    {
        return $this->organisationId;
    }

    /**
     * The API host, so a caller can build an absolute URL without guessing it.
     */
    public function apiUrl(): string
    {
        return $this->environment->apiUrl();
    }

    /**
     * The API host, with no organisation in it.
     *
     * Most endpoints live under `/organisations/{id}/...` and are built that
     * way by the requests, which take the organisation as an argument. The
     * base URL deliberately stops short of it because `/file-upload` is not
     * organisation-scoped: baking the organisation in here would put it on the
     * one endpoint that must not carry it.
     */
    public function resolveBaseUrl(): string
    {
        return $this->environment->apiUrl();
    }

    /**
     * Send a request, refreshing the access token once if it is refused.
     *
     * A 401 against a token we believed was valid means the token is gone —
     * revoked, or expired earlier than its `expires_in` claimed. Retrying
     * without forgetting it would send the same dead token again, so the two
     * always happen together. Server errors are retried as configured; a 429
     * is not, because the caller is told to wait a specific number of seconds
     * and only it knows whether waiting is acceptable.
     *
     * @param  Request  $request  The request to send.
     * @param  MockClient|null  $mockClient  Mock client, in tests.
     * @param  callable|null  $handleRetry  Overrides the retry rule entirely.
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        if ($handleRetry === null) {
            $refreshed = false;

            $handleRetry = function (Throwable $exception) use (&$refreshed): bool {
                if (! $exception instanceof RequestException) {
                    return false;
                }

                $status = $exception->getResponse()->status();

                if ($status === 401 && ! $refreshed) {
                    $refreshed = true;
                    $this->tokens->forget();

                    return true;
                }

                return $status >= 500;
            };

            $request->tries = max(2, (int) config('pingen.retry.times', 3));
            $request->retryInterval = max(0, (int) config('pingen.retry.delay', 500));
            $request->throwOnMaxTries = true;
        }

        return parent::send($request, $mockClient, $handleRetry);
    }

    /**
     * Attach the bearer token to every outgoing request.
     *
     * Deliberately here and not in {@see defaultHeaders()} — see the class
     * docblock. This method runs per request, so a token refreshed between two
     * attempts is the one that gets sent.
     *
     * @param  PendingRequest  $pendingRequest  The request about to be sent.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $pendingRequest->headers()->add('Authorization', 'Bearer '.$this->tokens->token());
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }
}
