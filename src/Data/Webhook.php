<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

/**
 * A registered webhook.
 *
 * The signing key is not returned by the API after creation — it is something
 * the caller chose and must keep. Nothing here can recover it.
 */
final class Webhook
{
    /**
     * @param  string  $id  UUID of the webhook.
     * @param  string  $eventCategory  Which events it receives.
     * @param  string  $url  Where deliveries are posted.
     * @param  array<string, mixed>  $raw  The whole resource object, for anything not modelled.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventCategory,
        public readonly string $url,
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

        return new self(
            id: (string) ($resource['id'] ?? ''),
            eventCategory: (string) ($attributes['event_category'] ?? ''),
            url: (string) ($attributes['url'] ?? ''),
            raw: $resource,
        );
    }

    /**
     * Build from a JSON:API document holding one webhook.
     *
     * @param  array<string, mixed>  $payload  The decoded response body.
     */
    public static function fromResponse(array $payload): self
    {
        /** @var array<string, mixed> $resource */
        $resource = (array) (($payload['data'] ?? $payload) ?: []);

        return self::fromResource($resource);
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
