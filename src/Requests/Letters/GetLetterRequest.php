<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Reads one letter.
 */
class GetLetterRequest extends Request
{
    protected Method $method = Method::GET;

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
        return '/organisations/'.$this->organisationId.'/deliveries/letters/'.$this->letterId;
    }
}
