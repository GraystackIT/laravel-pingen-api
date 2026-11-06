<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Support;

use GraystackIT\Pingen\Exceptions\ConfigurationException;

/**
 * Resolves the three print options against configuration and the allowlist.
 *
 * Two callers need the same resolution for different reasons — a letter being
 * sent, and a price being quoted — and both need it to fail before anything
 * leaves the process. Keeping it here means neither has to borrow the other's
 * object, and in particular a price quote no longer reports a problem in terms
 * of auto-sending a letter it was never asked to send.
 */
final class PrintOptions
{
    /**
     * Apply the configured defaults and check every value against its allowlist.
     *
     * @param  string|null  $deliveryProduct  Postal product, or null for the default.
     * @param  string|null  $printMode  Simplex or duplex, or null for the default.
     * @param  string|null  $printSpectrum  Colour or greyscale, or null for the default.
     * @return array{delivery_product: ?string, print_mode: ?string, print_spectrum: ?string}
     *
     * @throws ConfigurationException When a value is outside a configured allowlist.
     */
    public static function resolve(
        ?string $deliveryProduct,
        ?string $printMode,
        ?string $printSpectrum,
    ): array {
        $options = [
            'delivery_product' => $deliveryProduct ?? self::configString('pingen.defaults.delivery_product'),
            'print_mode' => $printMode ?? self::configString('pingen.defaults.print_mode'),
            'print_spectrum' => $printSpectrum ?? self::configString('pingen.defaults.print_spectrum'),
        ];

        foreach ($options as $key => $value) {
            self::assertAllowed($key, $value);
        }

        return $options;
    }

    /**
     * Demand that all three options are known, naming what needs them.
     *
     * @param  array{delivery_product: ?string, print_mode: ?string, print_spectrum: ?string}  $options  Resolved options.
     * @param  string  $because  What the options are needed for, as a sentence opening.
     *
     * @throws ConfigurationException When any of the three is still null.
     */
    public static function assertComplete(array $options, string $because): void
    {
        $missing = array_keys(array_filter($options, static fn (?string $value): bool => $value === null));

        if ($missing === []) {
            return;
        }

        throw new ConfigurationException(
            $because.' needs '.implode(', ', $missing).'. Pass '
            .(count($missing) === 1 ? 'it' : 'them').' to this call, or set the '
            .implode(', ', array_map(
                static fn (string $key): string => 'PINGEN_'.strtoupper($key),
                $missing,
            )).' default'.(count($missing) === 1 ? '' : 's').' in the environment.'
        );
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
    public static function assertAllowed(string $key, ?string $value): void
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
