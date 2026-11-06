<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Services;

use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Data\Organisation;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Requests\Organisations\ListOrganisationsRequest;
use GraystackIT\Pingen\Services\Concerns\MapsPingenErrors;
use Saloon\Exceptions\Request\RequestException;

/**
 * Reads the organisations the OAuth client can act for.
 */
class OrganisationService
{
    use MapsPingenErrors;

    /**
     * @param  PingenConnector  $connector  Any configured connector; this endpoint is not organisation-scoped.
     */
    public function __construct(private readonly PingenConnector $connector) {}

    /**
     * List every reachable organisation.
     *
     * @return array<int, Organisation>
     *
     * @throws PingenException When the API refuses.
     */
    public function list(): array
    {
        try {
            $response = $this->connector->send(new ListOrganisationsRequest);
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        return Organisation::collectionFromResponse((array) $response->json());
    }
}
