<?php

declare(strict_types=1);

use GraystackIT\Pingen\Data\Letter;
use GraystackIT\Pingen\Enums\LetterStatus;

it('reads a letter out of a JSON:API document', function (): void {
    $letter = Letter::fromResponse(letterResponse(['status' => 'sent', 'tracking_number' => 'TRACK-1']));

    expect($letter->id)->toBe('11111111-2222-4333-8444-555555555555')
        ->and($letter->status)->toBe(LetterStatus::Sent)
        ->and($letter->rawStatus)->toBe('sent')
        ->and($letter->fileOriginalName)->toBe('invoice.pdf')
        ->and($letter->filePages)->toBe(2)
        ->and($letter->country)->toBe('AT')
        ->and($letter->priceValue)->toBe(1.65)
        ->and($letter->priceCurrency)->toBe('CHF')
        ->and($letter->trackingNumber)->toBe('TRACK-1')
        ->and($letter->createdAt?->toDateString())->toBe('2026-11-06');
});

it('keeps an unrecognised status instead of throwing', function (): void {
    // Pingen extends its vocabulary without this package being released. A
    // status we have never seen must still be readable, not fatal.
    $letter = Letter::fromResponse(letterResponse(['status' => 'something_new_in_2027']));

    expect($letter->status)->toBe(LetterStatus::Unknown)
        ->and($letter->rawStatus)->toBe('something_new_in_2027');
});

it('survives a document with almost nothing in it', function (): void {
    $letter = Letter::fromResponse(['data' => ['id' => 'l-1', 'type' => 'letters']]);

    expect($letter->id)->toBe('l-1')
        ->and($letter->status)->toBe(LetterStatus::Unknown)
        ->and($letter->filePages)->toBeNull()
        ->and($letter->paperTypes)->toBe([])
        ->and($letter->submittedAt)->toBeNull();
});

it('reads a collection document', function (): void {
    $letters = Letter::collectionFromResponse([
        'data' => [
            letterResponse([], 'a-1')['data'],
            letterResponse(['status' => 'sent'], 'b-2')['data'],
        ],
    ]);

    expect($letters)->toHaveCount(2)
        ->and($letters[0]->id)->toBe('a-1')
        ->and($letters[1]->status)->toBe(LetterStatus::Sent);
});

it('keeps the whole resource for anything not modelled', function (): void {
    $letter = Letter::fromResponse(letterResponse(['some_future_field' => 'value']));

    expect($letter->raw['attributes']['some_future_field'])->toBe('value');
});

it('knows which statuses are drafts and which are final', function (): void {
    expect(LetterStatus::Draft->isDraft())->toBeTrue()
        ->and(LetterStatus::Sent->isDraft())->toBeFalse()
        ->and(LetterStatus::Sent->isFinal())->toBeTrue()
        ->and(LetterStatus::Cancelled->isFinal())->toBeTrue()
        ->and(LetterStatus::Processing->isFinal())->toBeFalse();
});
