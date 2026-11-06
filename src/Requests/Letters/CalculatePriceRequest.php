<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Letters;

use GraystackIT\Pingen\Requests\Concerns\BuildsJsonApiBody;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Asks what a letter would cost, without creating one.
 *
 * The one call that lets an application show a price before anything is spent,
 * which is why the whole package defaults to creating drafts rather than
 * auto-sending.
 */
class CalculatePriceRequest extends Request implements HasBody
{
    use BuildsJsonApiBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $organisationId  UUID of the owning organisation.
     * @param  string  $country  Destination country as an ISO 3166-1 alpha-2 code.
     * @param  string  $deliveryProduct  Postal product.
     * @param  string  $printMode  Simplex or duplex.
     * @param  string  $printSpectrum  Colour or greyscale.
     * @param  array<int, mixed>  $paperTypes  Paper types per sheet, as the API expects them.
     */
    public function __construct(
        private readonly string $organisationId,
        private readonly string $country,
        private readonly string $deliveryProduct,
        private readonly string $printMode,
        private readonly string $printSpectrum,
        private readonly array $paperTypes = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/organisations/'.$this->organisationId.'/deliveries/letters/price-calculator';
    }

    protected function jsonApiType(): string
    {
        // Not "letters": the price calculator is its own resource type, and
        // the API rejects the document outright when it is called anything
        // else.
        return 'letter_price_calculator';
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonApiAttributes(): array
    {
        return [
            'country' => $this->country,
            'delivery_product' => $this->deliveryProduct,
            'print_mode' => $this->printMode,
            'print_spectrum' => $this->printSpectrum,
            'paper_types' => $this->paperTypes === [] ? null : $this->paperTypes,
        ];
    }
}
