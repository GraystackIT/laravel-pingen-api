<?php

declare(strict_types=1);

use GraystackIT\Pingen\Data\FileUpload;

it('reads an upload slot out of a JSON:API document', function (): void {
    $slot = FileUpload::fromResponse(fileUploadResponse());

    expect($slot->url)->toBe('https://storage.example.test/upload/abc123')
        ->and($slot->urlSignature)->toBe('signature-abc123')
        ->and($slot->hasExpired())->toBeFalse();
});

it('knows when a slot has expired', function (): void {
    $slot = FileUpload::fromResponse(fileUploadResponse(now()->subMinute()->toIso8601String()));

    expect($slot->hasExpired())->toBeTrue();
});

it('treats a slot with no stated expiry as usable', function (): void {
    $slot = new FileUpload('https://storage.example.test/u', 'sig');

    expect($slot->hasExpired())->toBeFalse();
});
