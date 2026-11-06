<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

use Carbon\CarbonImmutable;
use GraystackIT\Pingen\Enums\LetterStatus;

/**
 * One letter, as the API describes it.
 *
 * `status` and `rawStatus` are both kept on purpose: the enum is what code
 * branches on, the string is what Pingen actually said. When the two disagree
 * — an unrecognised status — the enum reads {@see LetterStatus::Unknown} and
 * the string still carries the truth, so nothing is lost and nothing throws.
 */
final class Letter
{
    /**
     * @param  string  $id  UUID Pingen assigned.
     * @param  LetterStatus  $status  Lifecycle state, mapped.
     * @param  string  $rawStatus  Lifecycle state, exactly as returned.
     * @param  string  $fileOriginalName  File name as uploaded.
     * @param  int|null  $filePages  Page count once the file was processed.
     * @param  string|null  $addressPosition  Where the address window sits.
     * @param  string|null  $address  Address Pingen read off the page.
     * @param  string|null  $country  Destination country, ISO 3166-1 alpha-2.
     * @param  string|null  $priceCurrency  Currency of the price.
     * @param  float|null  $priceValue  Price, in major units of that currency.
     * @param  string|null  $deliveryProduct  Postal product in use.
     * @param  string|null  $printMode  Simplex or duplex.
     * @param  string|null  $printSpectrum  Colour or greyscale.
     * @param  array<int, mixed>  $paperTypes  Paper type per sheet.
     * @param  array<int, mixed>  $fonts  Fonts found in the file.
     * @param  string|null  $source  How the letter was created.
     * @param  string|null  $trackingNumber  Postal tracking number, when the product has one.
     * @param  array<string, mixed>  $metaData  Whatever the caller stored with it.
     * @param  CarbonImmutable|null  $submittedAt  When it went to production.
     * @param  CarbonImmutable|null  $createdAt  When the record was created.
     * @param  CarbonImmutable|null  $updatedAt  When it last changed.
     * @param  array<string, mixed>  $raw  The whole resource object, for anything not modelled.
     */
    public function __construct(
        public readonly string $id,
        public readonly LetterStatus $status,
        public readonly string $rawStatus,
        public readonly string $fileOriginalName = '',
        public readonly ?int $filePages = null,
        public readonly ?string $addressPosition = null,
        public readonly ?string $address = null,
        public readonly ?string $country = null,
        public readonly ?string $priceCurrency = null,
        public readonly ?float $priceValue = null,
        public readonly ?string $deliveryProduct = null,
        public readonly ?string $printMode = null,
        public readonly ?string $printSpectrum = null,
        public readonly array $paperTypes = [],
        public readonly array $fonts = [],
        public readonly ?string $source = null,
        public readonly ?string $trackingNumber = null,
        public readonly array $metaData = [],
        public readonly ?CarbonImmutable $submittedAt = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from a JSON:API document holding one letter.
     *
     * @param  array<string, mixed>  $payload  The decoded response body, or the resource object itself.
     */
    public static function fromResponse(array $payload): self
    {
        /** @var array<string, mixed> $resource */
        $resource = (array) (($payload['data'] ?? $payload) ?: []);

        return self::fromResource($resource);
    }

    /**
     * Build from a single JSON:API resource object.
     *
     * @param  array<string, mixed>  $resource  One `{id, type, attributes}` object.
     */
    public static function fromResource(array $resource): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (array) ($resource['attributes'] ?? []);

        $rawStatus = (string) ($attributes['status'] ?? '');

        return new self(
            id: (string) ($resource['id'] ?? ''),
            status: LetterStatus::tryFrom($rawStatus) ?? LetterStatus::Unknown,
            rawStatus: $rawStatus,
            fileOriginalName: (string) ($attributes['file_original_name'] ?? ''),
            filePages: isset($attributes['file_pages']) ? (int) $attributes['file_pages'] : null,
            addressPosition: self::stringOrNull($attributes['address_position'] ?? null),
            address: self::stringOrNull($attributes['address'] ?? null),
            country: self::stringOrNull($attributes['country'] ?? null),
            priceCurrency: self::stringOrNull($attributes['price_currency'] ?? null),
            priceValue: isset($attributes['price_value']) ? (float) $attributes['price_value'] : null,
            deliveryProduct: self::stringOrNull($attributes['delivery_product'] ?? null),
            printMode: self::stringOrNull($attributes['print_mode'] ?? null),
            printSpectrum: self::stringOrNull($attributes['print_spectrum'] ?? null),
            paperTypes: is_array($attributes['paper_types'] ?? null) ? $attributes['paper_types'] : [],
            fonts: is_array($attributes['fonts'] ?? null) ? $attributes['fonts'] : [],
            source: self::stringOrNull($attributes['source'] ?? null),
            trackingNumber: self::stringOrNull($attributes['tracking_number'] ?? null),
            metaData: is_array($attributes['meta_data'] ?? null) ? $attributes['meta_data'] : [],
            submittedAt: self::dateOrNull($attributes['submitted_at'] ?? null),
            createdAt: self::dateOrNull($attributes['created_at'] ?? null),
            updatedAt: self::dateOrNull($attributes['updated_at'] ?? null),
            raw: $resource,
        );
    }

    /**
     * Build a list from a JSON:API collection document.
     *
     * @param  array<string, mixed>  $payload  The decoded response body.
     * @return array<int, self>
     */
    public static function collectionFromResponse(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $resource): self => self::fromResource($resource),
            array_filter($data, 'is_array'),
        ));
    }

    /**
     * Read a value as a non-empty string, or null.
     *
     * @param  mixed  $value  The raw value.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * Read a value as a date, or null.
     *
     * @param  mixed  $value  The raw value.
     */
    private static function dateOrNull(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
