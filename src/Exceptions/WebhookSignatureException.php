<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Exceptions;

/**
 * Thrown when an inbound webhook cannot be trusted.
 *
 * Covers a missing header, a signature that does not match the signing key,
 * and a body that is not the JSON the signature was computed over.
 */
class WebhookSignatureException extends PingenException {}
