<?php

declare(strict_types=1);

use GraystackIT\Pingen\Data\LetterPrice;

it('reads the price calculator response', function (): void {
    // Captured from staging. The calculator names the figure `price`; reading
    // only `price_value` quietly reported 0.00 for a letter that costs money,
    // which is the worst shape a pricing bug can take.
    $price = LetterPrice::fromResponse([
        'data' => [
            'type' => 'letter_price_calculator',
            'id' => '5d97714c-991e-401a-8a95-743efed9f792',
            'attributes' => ['currency' => 'USD', 'price' => 2.01],
        ],
    ]);

    expect($price->value)->toBe(2.01)
        ->and($price->currency)->toBe('USD');
});

it('reads the price off a letter resource too', function (): void {
    $price = LetterPrice::fromResponse([
        'data' => ['attributes' => ['price_currency' => 'CHF', 'price_value' => 1.65]],
    ]);

    expect($price->value)->toBe(1.65)
        ->and($price->currency)->toBe('CHF');
});
