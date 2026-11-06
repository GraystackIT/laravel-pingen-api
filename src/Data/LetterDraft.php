<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

use GraystackIT\Pingen\Exceptions\ConfigurationException;

/**
 * Everything needed to send one letter, in one object.
 *
 * Sending is three HTTP calls, but a caller should describe a letter once.
 * This is that description: the bytes, a name, and the print options — each of
 * which may be left null to take the configured default, which is why an
 * invoice module can call the package with two arguments.
 *
 * {@see resolved()} is where the defaults are applied and the values checked
 * against the configured allowlist, so a typo in a product name fails before
 * anything is uploaded rather than at the create call with the file already in
 * storage.
 */
final class LetterDraft
{
    /**
     * @param  string  $contents  The raw file bytes.
     * @param  string  $fileName  File name shown in the Pingen dashboard.
     * @param  string|null  $addressPosition  Where the address window sits.
     * @param  bool|null  $autoSend  Submit immediately instead of creating a draft.
     * @param  string|null  $deliveryProduct  Postal product.
     * @param  string|null  $printMode  Simplex or duplex.
     * @param  string|null  $printSpectrum  Colour or greyscale.
     * @param  array<string, mixed>|null  $metaData  Arbitrary data stored with the letter.
     * @param  string  $contentType  Media type of the file.
     */
    public function __construct(
        public readonly string $contents,
        public readonly string $fileName,
        public readonly ?string $addressPosition = null,
        public readonly ?bool $autoSend = null,
        public readonly ?string $deliveryProduct = null,
        public readonly ?string $printMode = null,
        public readonly ?string $printSpectrum = null,
        public readonly ?array $metaData = null,
        public readonly string $contentType = 'application/pdf',
    ) {}

    /**
     * Build a draft from a file on disk.
     *
     * @param  string  $path  Absolute path to the file.
     * @param  string|null  $fileName  Name to show; defaults to the file's own.
     * @param  array<string, mixed>|null  $metaData  Arbitrary data stored with the letter.
     *
     * @throws ConfigurationException When the file cannot be read.
     */
    public static function fromPath(string $path, ?string $fileName = null, ?array $metaData = null): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new ConfigurationException("The file [{$path}] could not be read.");
        }

        return new self(
            contents: $contents,
            fileName: $fileName ?? basename($path),
            metaData: $metaData,
        );
    }

    /**
     * The print options with configuration defaults applied and validated.
     *
     * @return array{address_position: string, auto_send: bool, delivery_product: ?string, print_mode: ?string, print_spectrum: ?string}
     *
     * @throws ConfigurationException When a value is outside the configured allowlist.
     */
    public function resolved(): array
    {
        $addressPosition = $this->addressPosition ?? (string) config('pingen.defaults.address_position', 'left');
        $autoSend = $this->autoSend ?? (bool) config('pingen.defaults.auto_send', false);
        $deliveryProduct = $this->deliveryProduct ?? self::configString('pingen.defaults.delivery_product');
        $printMode = $this->printMode ?? self::configString('pingen.defaults.print_mode');
        $printSpectrum = $this->printSpectrum ?? self::configString('pingen.defaults.print_spectrum');

        self::assertAllowed('address_position', $addressPosition);
        self::assertAllowed('delivery_product', $deliveryProduct);
        self::assertAllowed('print_mode', $printMode);
        self::assertAllowed('print_spectrum', $printSpectrum);

        if ($autoSend && ($deliveryProduct === null || $printMode === null || $printSpectrum === null)) {
            throw new ConfigurationException(
                'Auto-sending a letter requires delivery_product, print_mode and print_spectrum. '
                .'Pass them to the draft or set the PINGEN_DELIVERY_PRODUCT, PINGEN_PRINT_MODE and '
                .'PINGEN_PRINT_SPECTRUM defaults.'
            );
        }

        return [
            'address_position' => $addressPosition,
            'auto_send' => $autoSend,
            'delivery_product' => $deliveryProduct,
            'print_mode' => $printMode,
            'print_spectrum' => $printSpectrum,
        ];
    }

    /**
     * Check one value against its allowlist.
     *
     * An empty allowlist means "anything goes", which is the shipped default
     * for the three print options: the catalogue of postal products is
     * Pingen's and changes without this package being released, so a hard list
     * here would eventually block a product that works.
     *
     * @param  string  $key  Which option is being checked.
     * @param  string|null  $value  The value to check; null is always allowed.
     *
     * @throws ConfigurationException When the value is not in a non-empty allowlist.
     */
    private static function assertAllowed(string $key, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $allowed = config('pingen.allowed.'.$key, []);

        if (! is_array($allowed) || $allowed === [] || in_array($value, $allowed, true)) {
            return;
        }

        throw new ConfigurationException(
            "[{$value}] is not an allowed {$key}. Allowed: ".implode(', ', array_map('strval', $allowed)).'.'
        );
    }

    /**
     * Read a configuration value as a non-empty string, or null.
     *
     * @param  string  $key  Configuration key.
     */
    private static function configString(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
