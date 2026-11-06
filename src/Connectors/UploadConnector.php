<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Connectors;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

/**
 * Uploads a file to the signed URL Pingen hands out.
 *
 * A separate connector, and deliberately a bare one: the signed URL points at
 * object storage, not at Pingen, and the signature in the query string is the
 * whole authorisation. Sending our bearer token there would hand a third-party
 * host a credential good for the entire account — silently, since the upload
 * would succeed either way and nothing in the response would mention it.
 *
 * The base URL is empty because the request carries the absolute signed URL.
 */
class UploadConnector extends Connector
{
    use AlwaysThrowOnErrors;

    public function resolveBaseUrl(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [];
    }
}
