<?php

declare(strict_types=1);

use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Support\PrintOptions;

beforeEach(function (): void {
    config()->set('pingen.defaults.delivery_product', null);
    config()->set('pingen.defaults.print_mode', null);
    config()->set('pingen.defaults.print_spectrum', null);
});

it('names the price call, not auto-sending, when a quote lacks print options', function (): void {
    // calculatePrice() once borrowed a draft with autoSend: true purely to
    // resolve the options, and so reported a problem with auto-sending a
    // letter the caller had never asked to send.
    try {
        PrintOptions::assertComplete(PrintOptions::resolve(null, null, null), 'Quoting a price');

        $this->fail('The incomplete options should have thrown.');
    } catch (ConfigurationException $e) {
        expect($e->getMessage())
            ->toStartWith('Quoting a price needs delivery_product, print_mode, print_spectrum')
            ->toContain('PINGEN_DELIVERY_PRODUCT')
            ->not->toContain('Auto-sending');
    }
});

it('names only the options actually missing', function (): void {
    config()->set('pingen.defaults.delivery_product', 'cheap');
    config()->set('pingen.defaults.print_mode', 'simplex');

    try {
        PrintOptions::assertComplete(PrintOptions::resolve(null, null, null), 'Quoting a price');

        $this->fail('The missing spectrum should have thrown.');
    } catch (ConfigurationException $e) {
        expect($e->getMessage())
            ->toContain('needs print_spectrum.')
            ->toContain('PINGEN_PRINT_SPECTRUM default')
            ->not->toContain('delivery_product');
    }
});

it('creates a draft without any print options at all', function (): void {
    // The whole point of a draft: it is described now and priced later, so
    // none of the three is required until it is submitted.
    $resolved = (new LetterDraft(contents: 'x', fileName: 'a.pdf'))->resolved();

    expect($resolved['auto_send'])->toBeFalse()
        ->and($resolved['delivery_product'])->toBeNull()
        ->and($resolved['print_mode'])->toBeNull()
        ->and($resolved['print_spectrum'])->toBeNull();
});

it('still refuses to auto-send without the print options', function (): void {
    (new LetterDraft(contents: 'x', fileName: 'a.pdf', autoSend: true))->resolved();
})->throws(ConfigurationException::class, 'Auto-sending a letter needs');

it('applies the configured defaults to a draft', function (): void {
    config()->set('pingen.defaults.delivery_product', 'cheap');
    config()->set('pingen.defaults.print_mode', 'simplex');
    config()->set('pingen.defaults.print_spectrum', 'grayscale');

    $resolved = (new LetterDraft(contents: 'x', fileName: 'a.pdf'))->resolved();

    expect($resolved['delivery_product'])->toBe('cheap')
        ->and($resolved['print_mode'])->toBe('simplex')
        ->and($resolved['print_spectrum'])->toBe('grayscale');
});

it('lets an explicit value beat the configured default', function (): void {
    config()->set('pingen.defaults.print_spectrum', 'grayscale');

    $resolved = (new LetterDraft(contents: 'x', fileName: 'a.pdf', printSpectrum: 'color'))->resolved();

    expect($resolved['print_spectrum'])->toBe('color');
});
