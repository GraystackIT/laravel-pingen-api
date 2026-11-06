<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Webhooks;

use GraystackIT\Pingen\Requests\Concerns\BuildsJsonApiBody;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Registers a webhook for one event category.
 *
 * The signing key is chosen by the caller, not issued by Pingen: whatever is
 * sent here is what deliveries will be signed with, so it has to be stored
 * somewhere the receiving side can read it before the first delivery arrives.
 */
class CreateWebhookRequest extends Request implements HasBody
{
    use BuildsJsonApiBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $eventCategory  Which events to receive.
     * @param  string  $url  Publicly reachable endpoint deliveries are posted to.
     * @param  string  $signingKey  Shared secret deliveries will be signed with.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $eventCategory,
        private readonly string $url,
        private readonly string $signingKey,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/webhooks';
    }

    protected function jsonApiType(): string
    {
        return 'webhooks';
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonApiAttributes(): array
    {
        return [
            'event_category' => $this->eventCategory,
            'url' => $this->url,
            'signing_key' => $this->signingKey,
        ];
    }
}
