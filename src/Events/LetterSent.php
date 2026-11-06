<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pingen handed the letter to the postal service.
 */
class LetterSent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $letterId  UUID of the letter.
     * @param  string  $status  Status as reported, before any mapping.
     * @param  array<string, mixed>  $payload  The whole webhook body.
     */
    public function __construct(
        public readonly string $letterId,
        public readonly string $status,
        public readonly array $payload = [],
    ) {}
}
