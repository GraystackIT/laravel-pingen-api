<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

/**
 * An organisation the OAuth client can act for.
 *
 * Only the identifying fields are modelled. Everything else an account
 * carries — plan, billing, limits — is readable from {@see $raw} and is not
 * this package's business.
 */
final class Organisation
{
    /**
     * @param  string  $id  UUID of the organisation.
     * @param  string  $name  Display name.
     * @param  string|null  $status  Account status, as returned.
     * @param  array<string, mixed>  $raw  The whole resource object.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $status = null,
        public readonly array $raw = [],
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

        $status = $attributes['status'] ?? null;

        return new self(
            id: (string) ($resource['id'] ?? ''),
            name: (string) ($attributes['name'] ?? ''),
            status: is_string($status) && $status !== '' ? $status : null,
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
}
