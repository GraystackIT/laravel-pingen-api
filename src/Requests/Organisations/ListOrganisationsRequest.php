<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Organisations;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Lists the organisations the OAuth client can reach.
 *
 * Useful once: to find the UUID that goes into `PINGEN_ORGANISATION_ID`.
 */
class ListOrganisationsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/organisations';
    }
}
