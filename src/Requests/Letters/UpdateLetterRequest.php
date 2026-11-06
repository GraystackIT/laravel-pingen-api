<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use GraystackIT\Pingen\Requests\Concerns\BuildsJsonApiBody;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Edits a draft letter.
 *
 * Only meaningful before the letter is submitted; once it is in production
 * there is nothing left to change.
 */
class UpdateLetterRequest extends Request implements HasBody
{
    use BuildsJsonApiBody;

    protected Method $method = Method::PATCH;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $letterId  UUID of the draft letter.
     * @param  array<string, mixed>  $attributes  Attributes to change.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $letterId,
        private readonly array $attributes,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters/'.$this->letterId;
    }

    protected function jsonApiId(): ?string
    {
        return $this->letterId;
    }

    protected function jsonApiType(): string
    {
        return 'letters';
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonApiAttributes(): array
    {
        return $this->attributes;
    }
}
