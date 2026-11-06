<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Data;

use GraystackIT\Pingen\Exceptions\ConfigurationException;
use GraystackIT\Pingen\Support\PrintOptions;

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

        PrintOptions::assertAllowed('address_position', $addressPosition);

        $options = PrintOptions::resolve($this->deliveryProduct, $this->printMode, $this->printSpectrum);

        if ($autoSend) {
            // Only auto-sending needs all three up front: a draft is created
            // without them and names them at the later submit call.
            PrintOptions::assertComplete($options, 'Auto-sending a letter');
        }

        return [
            'address_position' => $addressPosition,
            'auto_send' => $autoSend,
            ...$options,
        ];
    }
}
