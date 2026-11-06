<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use GraystackIT\Pingen\Requests\Concerns\BuildsJsonApiBody;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Submits a draft letter to production.
 *
 * This is the call that costs money and after which the letter can no longer
 * be deleted, only cancelled — and only for as long as it has not been
 * printed.
 */
class SendLetterRequest extends Request implements HasBody
{
    use BuildsJsonApiBody;

    protected Method $method = Method::PATCH;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $letterId  UUID of the draft letter.
     * @param  string  $deliveryProduct  Postal product to send with.
     * @param  string  $printMode  Simplex or duplex.
     * @param  string  $printSpectrum  Colour or greyscale.
     * @param  array<string, mixed>|null  $metaData  Arbitrary data stored with the letter.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $letterId,
        private readonly string $deliveryProduct,
        private readonly string $printMode,
        private readonly string $printSpectrum,
        private readonly ?array $metaData = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters/'.$this->letterId.'/send';
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
        return [
            'delivery_product' => $this->deliveryProduct,
            'print_mode' => $this->printMode,
            'print_spectrum' => $this->printSpectrum,
            'meta_data' => $this->metaData,
        ];
    }
}
