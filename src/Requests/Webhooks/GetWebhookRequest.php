<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Webhooks;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Reads one registered webhook.
 */
class GetWebhookRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $webhookId  UUID of the webhook.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $webhookId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/webhooks/'.$this->webhookId;
    }
}
