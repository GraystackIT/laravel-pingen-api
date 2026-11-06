<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

/**
 * What a letter would cost.
 *
 * The value is in major units of the currency, as the API returns it. An
 * application storing money in integer cents converts at its own boundary
 * rather than here, so nothing is rounded twice.
 */
final class LetterPrice
{
    /**
     * @param  float  $value  Price in major units.
     * @param  string  $currency  ISO 4217 currency code.
     * @param  array<string, mixed>  $raw  The whole resource object, for anything not modelled.
     */
    public function __construct(
        public readonly float $value,
        public readonly string $currency,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from a JSON:API document.
     *
     * @param  array<string, mixed>  $payload  The decoded response body.
     */
    public static function fromResponse(array $payload): self
    {
        /** @var array<string, mixed> $resource */
        $resource = (array) (($payload['data'] ?? $payload) ?: []);
        /** @var array<string, mixed> $attributes */
        $attributes = (array) ($resource['attributes'] ?? $resource);

        return new self(
            // The price calculator answers with `price`; a letter resource
            // carries the same figure as `price_value`. Both are read so one
            // object can describe either, and a quote no longer silently
            // reports 0.00 for a letter that costs money.
            value: (float) ($attributes['price'] ?? $attributes['price_value'] ?? $attributes['value'] ?? 0.0),
            currency: (string) ($attributes['price_currency'] ?? $attributes['currency'] ?? ''),
            raw: $resource,
        );
    }
}
