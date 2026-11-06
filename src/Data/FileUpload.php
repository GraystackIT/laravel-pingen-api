<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

use Carbon\CarbonImmutable;

/**
 * A slot to upload one file into.
 *
 * Short-lived by design. The signature is quoted back when the letter is
 * created, which is how Pingen knows the file at that URL is the one we
 * uploaded rather than anything else that happens to sit there.
 */
final class FileUpload
{
    /**
     * @param  string  $url  Absolute signed URL to PUT the file to.
     * @param  string  $urlSignature  Signature quoted back when creating the letter.
     * @param  CarbonImmutable|null  $expiresAt  When the slot stops accepting the file.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $urlSignature,
        public readonly ?CarbonImmutable $expiresAt = null,
    ) {}

    /**
     * Build from a JSON:API document.
     *
     * @param  array<string, mixed>  $payload  The decoded response body.
     */
    public static function fromResponse(array $payload): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (array) (($payload['data']['attributes'] ?? $payload['attributes'] ?? $payload) ?: []);

        $expiresAt = $attributes['expires_at'] ?? null;

        return new self(
            url: (string) ($attributes['url'] ?? ''),
            urlSignature: (string) ($attributes['url_signature'] ?? ''),
            expiresAt: is_string($expiresAt) && $expiresAt !== ''
                ? CarbonImmutable::parse($expiresAt)
                : null,
        );
    }

    /**
     * Whether the slot can no longer be used.
     *
     * Checked before the bytes are sent rather than after: a queued send can
     * easily be built before the slot expires and run after, and an expired
     * PUT fails at the storage host with an error that says nothing about
     * Pingen.
     */
    public function hasExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }
}
