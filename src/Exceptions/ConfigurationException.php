<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Exceptions;

/**
 * Thrown when the package is asked to work without the configuration it needs.
 *
 * A missing client id, a missing organisation UUID, or a print option outside
 * the configured allowlist. Raised before any request leaves, so a
 * misconfigured deployment fails on the first call with a sentence naming the
 * key rather than on a 401 from Pingen.
 */
class ConfigurationException extends PingenException {}
