<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Cancels a submitted letter before it is printed.
 *
 * Distinct from deleting: a draft is deleted because it never existed as post,
 * a submitted letter is cancelled because it did and the window to stop it is
 * short. Which of the two applies is the status, and an application that
 * meters sends usually gives an allowance back here but not on a delivery
 * failure.
 */
class CancelLetterRequest extends Request
{
    protected Method $method = Method::PATCH;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $letterId  UUID of the letter.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $letterId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters/'.$this->letterId.'/cancel';
    }
}
