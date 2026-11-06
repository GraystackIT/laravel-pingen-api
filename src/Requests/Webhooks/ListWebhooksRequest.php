<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Webhooks;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Lists the webhooks registered for an organisation.
 */
class ListWebhooksRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     */
    public function __construct(private readonly string $organisationId) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/webhooks';
    }
}
