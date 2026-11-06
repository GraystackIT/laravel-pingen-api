<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

use Carbon\CarbonImmutable;

/**
 * One tracking event in a letter's history.
 *
 * `emittedAt` is when the event happened at the producer; `createdAt` is when
 * Pingen recorded it. They differ, sometimes by hours, and a timeline should
 * be ordered by the first.
 */
final class LetterEvent
{
    /**
     * @param  string  $id  UUID of the event.
     * @param  string  $code  Machine-readable event code.
     * @param  string  $name  Human-readable event name.
     * @param  string|null  $producer  Who reported it, e.g. the postal service.
     * @param  string|null  $location  Where it happened.
     * @param  bool  $hasImage  Whether a scan is attached.
     * @param  array<string, mixed>  $data  Anything else the producer sent.
     * @param  CarbonImmutable|null  $emittedAt  When it happened.
     * @param  CarbonImmutable|null  $createdAt  When Pingen recorded it.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $producer = null,
        public readonly ?string $location = null,
        public readonly bool $hasImage = false,
        public readonly array $data = [],
        public readonly ?CarbonImmutable $emittedAt = null,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {}

    /**
     * Build from a single JSON:API resource object.
     *
     * @param  array<string, mixed>  $resource  One `{id, type, attributes}` object.
     */
    public static function fromResource(array $resource): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (array) ($resource['attributes'] ?? []);

        return new self(
            id: (string) ($resource['id'] ?? ''),
            code: (string) ($attributes['code'] ?? ''),
            name: (string) ($attributes['name'] ?? ''),
            producer: self::stringOrNull($attributes['producer'] ?? null),
            location: self::stringOrNull($attributes['location'] ?? null),
            hasImage: (bool) ($attributes['has_image'] ?? false),
            data: is_array($attributes['data'] ?? null) ? $attributes['data'] : [],
            emittedAt: self::dateOrNull($attributes['emitted_at'] ?? null),
            createdAt: self::dateOrNull($attributes['created_at'] ?? null),
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
        return is_string($value) && $value !== '' ? $value : null;
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
