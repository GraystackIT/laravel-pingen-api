<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Services\Concerns;

use GraystackIT\Pingen\Exceptions\LetterRejectedException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Exceptions\RateLimitExceededException;
use Saloon\Exceptions\Request\RequestException;

/**
 * Turns a failed request into the most specific exception that fits.
 *
 * The distinction is not cosmetic. An application that meters sends has to
 * decide, at the catch site, whether to give an allowance back and stop, or to
 * wait and try again — and those are opposite reactions to a 422 and a 429.
 * Leaving every failure as one class pushes that decision into string
 * matching on the message.
 */
trait MapsPingenErrors
{
    /**
     * Map a Saloon request failure onto a package exception.
     *
     * @param  RequestException  $exception  The exception Saloon threw.
     * @param  bool  $rejectable  Whether a 4xx here means the input was refused.
     */
    protected function mapException(RequestException $exception, bool $rejectable = false): PingenException
    {
        $status = $exception->getResponse()->status();

        if ($status === 429) {
            return RateLimitExceededException::fromRequestException($exception);
        }

        if ($rejectable && in_array($status, [400, 409, 422], true)) {
            return LetterRejectedException::fromRequestException($exception);
        }

        return PingenException::fromRequestException($exception);
    }
}
