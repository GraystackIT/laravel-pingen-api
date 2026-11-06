<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Services;

use GraystackIT\Pingen\Connectors\PingenConnector;
use GraystackIT\Pingen\Data\LetterEvent;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Requests\LetterEvents\ListLetterEventsRequest;
use GraystackIT\Pingen\Services\Concerns\MapsPingenErrors;
use Saloon\Exceptions\Request\RequestException;

/**
 * Reads the tracking history of a letter.
 */
class LetterEventService
{
    use MapsPingenErrors;

    /**
     * @param  PingenConnector  $connector  Connector bound to the organisation.
     */
    public function __construct(private readonly PingenConnector $connector) {}

    /**
     * List the events recorded for one letter.
     *
     * @param  string  $letterId  UUID of the letter.
     * @param  int|null  $page  Page number, 1-based.
     * @param  int|null  $limit  Events per page.
     * @return array<int, LetterEvent>
     *
     * @throws PingenException When the API refuses.
     */
    public function list(string $letterId, ?int $page = null, ?int $limit = null): array
    {
        try {
            $response = $this->connector->send(new ListLetterEventsRequest(
                organisationId: $this->connector->organisationId(),
                letterId: $letterId,
                page: $page,
                limit: $limit,
            ));
        } catch (RequestException $exception) {
            throw $this->mapException($exception);
        }

        return LetterEvent::collectionFromResponse((array) $response->json());
    }
}
