<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The letter reached a status this package does not model on its own. Carries the raw status so an application can react to something newer than this release.
 */
class LetterStatusChanged
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
