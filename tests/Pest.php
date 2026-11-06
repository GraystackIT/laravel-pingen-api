<?php

declare(strict_types=1);

use GraystackIT\Pingen\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class)->in('Feature', 'Unit');

// Feature tests touch the package tables; unit tests stay pure.
uses(RefreshDatabase::class)->in('Feature');

/**
 * Put a usable access token in the cache so a test need not mock the identity
 * server. The tests that are about the token clear it first.
 */
function seedAccessToken(string $token = 'test-access-token'): void
{
    Cache::flush();

    app(GraystackIT\Pingen\Auth\AccessTokenStore::class);

    Cache::put(accessTokenCacheKey(), $token, 3600);
}

/**
 * The cache key the token store uses for the test configuration.
 */
function accessTokenCacheKey(): string
{
    $prefix = (string) config('pingen.token_cache.key', 'pingen:access-token');
    $scope = hash(
        'sha256',
        (string) config('pingen.client_id').'|https://identity-staging.pingen.com|',
    );

    return $prefix.':'.substr($scope, 0, 16);
}

/**
 * A JSON:API document holding one letter.
 *
 * Invented data throughout. A real Pingen payload carries a recipient postal
 * address, which is personal data and must never be committed to a repository
 * that will be public.
 *
 * @param  array<string, mixed>  $attributes  Attributes to override.
 * @return array<string, mixed>
 */
function letterResponse(array $attributes = [], string $id = '11111111-2222-4333-8444-555555555555'): array
{
    return [
        'data' => [
            'id' => $id,
            'type' => 'letters',
            'attributes' => array_merge([
                'status' => 'draft',
                'file_original_name' => 'invoice.pdf',
                'file_pages' => 2,
                'address_position' => 'left',
                'address' => "Test Recipient\nExample Street 1\n1010 Example City",
                'country' => 'AT',
                'price_currency' => 'CHF',
                'price_value' => 1.65,
                'delivery_product' => 'cheap',
                'print_mode' => 'simplex',
                'print_spectrum' => 'grayscale',
                'paper_types' => [],
                'fonts' => [],
                'source' => 'api',
                'tracking_number' => null,
                'meta_data' => [],
                'submitted_at' => null,
                'created_at' => '2026-11-06T09:00:00+00:00',
                'updated_at' => '2026-11-06T09:00:00+00:00',
            ], $attributes),
        ],
    ];
}

/**
 * A JSON:API document holding an upload slot.
 *
 * @return array<string, mixed>
 */
function fileUploadResponse(?string $expiresAt = null): array
{
    return [
        'data' => [
            'id' => 'upload-1',
            'type' => 'file_uploads',
            'attributes' => [
                'url' => 'https://storage.example.test/upload/abc123',
                'url_signature' => 'signature-abc123',
                'expires_at' => $expiresAt ?? now()->addHour()->toIso8601String(),
            ],
        ],
    ];
}

/**
 * A JSON:API error document, the shape Pingen refuses with.
 *
 * @return array<string, mixed>
 */
function errorResponse(string $code, string $detail, ?string $pointer = null): array
{
    return [
        'errors' => [[
            'code' => $code,
            'title' => '',
            'detail' => $detail,
            'source' => ['pointer' => $pointer, 'parameter' => null],
        ]],
    ];
}

/**
 * Sign a body the way Pingen signs a webhook delivery.
 */
function pingenSignature(string $body, string $key): string
{
    return hash_hmac('sha256', $body, $key);
}

/**
 * A webhook delivery body.
 *
 * @param  array<string, mixed>  $attributes  Attributes to override.
 * @return array<string, mixed>
 */
function webhookPayload(
    string $status = 'sent',
    string $eventId = 'event-1',
    string $letterId = '11111111-2222-4333-8444-555555555555',
    array $attributes = [],
): array {
    return [
        'data' => [
            'id' => $eventId,
            'type' => 'webhook_events',
            'attributes' => array_merge([
                'event_category' => 'letters',
                'status' => $status,
            ], $attributes),
            'relationships' => [
                'letter' => ['data' => ['id' => $letterId, 'type' => 'letters']],
            ],
        ],
    ];
}
