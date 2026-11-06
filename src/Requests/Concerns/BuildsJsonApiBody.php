<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Requests\Concerns;

use Saloon\Traits\Body\HasStringBody;

/**
 * Wraps request attributes in the JSON:API envelope Pingen expects.
 *
 * Every write to the API is `{"data": {"type": ..., "attributes": {...}}}`
 * rather than a flat object, so building that by hand in each request class
 * would be the same six lines fifteen times.
 *
 * The body is encoded here rather than through Saloon's `HasJsonBody`, which
 * would set `Content-Type: application/json` and override the
 * `application/vnd.api+json` the API requires. Encoding it ourselves also
 * means the bytes on the wire are exactly what we built, which matters for
 * anything signed or compared in a test.
 *
 * Null attributes are dropped. The API distinguishes an absent attribute (use
 * the account default) from an explicit null (unset it), and every optional
 * argument in this package means the former.
 */
trait BuildsJsonApiBody
{
    use HasStringBody;

    /**
     * The JSON:API resource type of the body, for example "letters".
     */
    abstract protected function jsonApiType(): string;

    /**
     * The attributes to send, before nulls are dropped.
     *
     * @return array<string, mixed>
     */
    abstract protected function jsonApiAttributes(): array;

    /**
     * Relationships to send alongside the attributes.
     *
     * @return array<string, mixed>
     */
    protected function jsonApiRelationships(): array
    {
        return [];
    }

    /**
     * The encoded JSON:API document.
     */
    protected function defaultBody(): ?string
    {
        $data = [
            'type' => $this->jsonApiType(),
            'attributes' => array_filter(
                $this->jsonApiAttributes(),
                static fn (mixed $value): bool => $value !== null,
            ),
        ];

        $relationships = $this->jsonApiRelationships();

        if ($relationships !== []) {
            $data['relationships'] = $relationships;
        }

        return json_encode(['data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
