<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Events;

use GraystackIT\Pingen\Data\Letter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A letter was created, as a draft or already submitted.
 */
class LetterCreated
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
