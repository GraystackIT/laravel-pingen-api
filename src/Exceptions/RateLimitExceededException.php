<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * Thrown when Pingen answers 429.
 *
 * The headers are carried along rather than only the message, because the only
 * useful reaction to this is to wait exactly as long as the response says —
 * guessing a backoff either wastes time or walks straight into the next 429.
 */
class RateLimitExceededException extends PingenException
{
    /**
     * Seconds to wait before retrying, from the `Retry-After` header.
     */
    public int $retryAfter = 0;

    /**
     * Requests allowed in the current window, from `X-RateLimit-Limit`.
     */
    public int $limit = 0;

    /**
     * Requests left in the current window, from `X-RateLimit-Remaining`.
     */
    public int $remaining = 0;

    /**
     * Unix timestamp at which the window resets, from `X-RateLimit-Reset`.
     */
    public int $resetsAt = 0;

    /**
     * Build the exception from a 429 response, reading its rate-limit headers.
     *
     * @param  RequestException  $exception  The exception Saloon threw.
     */
    public static function fromRequestException(RequestException $exception): static
    {
        $instance = parent::fromRequestException($exception);

        $headers = $exception->getResponse()->headers();

        $instance->retryAfter = (int) $headers->get('Retry-After', 0);
        $instance->limit = (int) $headers->get('X-RateLimit-Limit', 0);
        $instance->remaining = (int) $headers->get('X-RateLimit-Remaining', 0);
        $instance->resetsAt = (int) $headers->get('X-RateLimit-Reset', 0);

        return $instance;
    }
}
