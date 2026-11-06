<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Exceptions;

use RuntimeException;
use Saloon\Exceptions\Request\RequestException;
use Throwable;

/**
 * Base exception for every failure this package reports.
 *
 * Pingen answers in JSON:API, so an error body is `{"errors": [{...}, ...]}`
 * rather than one message. Flattening that into a readable sentence is the
 * whole reason this class exists: without it every failure surfaces as
 * "Client error: 422", which tells a caller nothing about which field was
 * wrong.
 */
class PingenException extends RuntimeException
{
    /**
     * @param  string  $message  Human-readable description of the failure.
     * @param  int  $status  HTTP status code, or 0 when the failure was client-side.
     * @param  array<int, array<string, mixed>>  $errors  The JSON:API error objects, as returned.
     * @param  Throwable|null  $previous  The exception this one wraps.
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Build an exception from a failed Saloon request.
     *
     * @param  RequestException  $exception  The exception Saloon threw.
     */
    public static function fromRequestException(RequestException $exception): static
    {
        $response = $exception->getResponse();
        $status = $response->status();

        /** @var array<string, mixed> $body */
        $body = (array) ($response->json() ?? []);
        /** @var array<int, array<string, mixed>> $errors */
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];

        $message = self::describe($errors, $status, $exception->getMessage());

        return new static($message, $status, $errors, $exception);
    }

    /**
     * Turn JSON:API error objects into one sentence.
     *
     * @param  array<int, array<string, mixed>>  $errors  The error objects.
     * @param  int  $status  HTTP status code.
     * @param  string  $fallback  Message to use when the body carries no errors.
     */
    protected static function describe(array $errors, int $status, string $fallback): string
    {
        $parts = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $detail = self::stringOrNull($error['detail'] ?? null)
                ?? self::stringOrNull($error['title'] ?? null)
                ?? self::stringOrNull($error['code'] ?? null);

            if ($detail === null) {
                continue;
            }

            $pointer = self::stringOrNull($error['source']['pointer'] ?? null);

            $parts[] = $pointer === null ? $detail : "{$pointer}: {$detail}";
        }

        if ($parts === []) {
            return "Pingen request failed with status {$status}: {$fallback}";
        }

        return "Pingen request failed with status {$status}: ".implode('; ', $parts);
    }

    /**
     * Read a value as a non-empty string, or null.
     *
     * @param  mixed  $value  The raw value.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
