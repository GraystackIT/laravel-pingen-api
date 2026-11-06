<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\LetterEvents;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Lists the tracking events recorded for one letter.
 *
 * The same history the webhooks announce, but pullable — which is what an
 * application needs when a webhook was missed or when it wants to show the
 * full trail rather than only the latest state.
 */
class ListLetterEventsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $letterId  UUID of the letter.
     * @param  int|null  $page  Page number, 1-based.
     * @param  int|null  $limit  Events per page.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $letterId,
        private readonly ?int $page = null,
        private readonly ?int $limit = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters/'.$this->letterId.'/events';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->page !== null) {
            $query['page[number]'] = $this->page;
        }

        if ($this->limit !== null) {
            $query['page[limit]'] = $this->limit;
        }

        return $query;
    }
}
