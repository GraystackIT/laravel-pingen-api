<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Events;

use GraystackIT\Pingen\Data\Letter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A letter was handed to production. This is the point at which it costs money.
 */
class LetterSubmitted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Letter  $letter  The letter as the API returned it.
     */
    public function __construct(
        public readonly Letter $letter,
    ) {}
}
