<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Enums;

/**
 * The lifecycle state of a letter.
 *
 * Pingen documents `status` as an open set and adds to it without this package
 * being released, so every unrecognised value becomes {@see self::Unknown}
 * while {@see \GraystackIT\Pingen\Data\Letter::$rawStatus} keeps the string
 * exactly as it arrived. An application that meets a status this enum has
 * never heard of can therefore still read, store and display it, rather than
 * crashing on a value that is perfectly valid at the other end.
 *
 * The cases below are reconstructed from the official SDK and the dashboard
 * vocabulary rather than from a published list. Confirm them against a staging
 * account before relying on a specific one; the fallback above is what makes
 * that safe to do after release.
 */
enum LetterStatus: string
{
    /**
     * Created but not submitted. Costs nothing and can still be deleted.
     */
    case Draft = 'draft';

    /**
     * The uploaded file is being checked and rendered.
     */
    case Processing = 'processing';

    /**
     * Pingen refused the file. The letter will not be sent.
     */
    case Invalid = 'invalid';

    /**
     * Submitted and waiting to be printed.
     */
    case Sending = 'sending';

    /**
     * Handed to the postal service.
     */
    case Sent = 'sent';

    /**
     * Stopped before printing.
     */
    case Cancelled = 'cancelled';

    /**
     * The send failed for good.
     */
    case Failed = 'failed';

    /**
     * A status this version does not know.
     */
    case Unknown = 'unknown';

    /**
     * Whether the letter can still be changed or deleted without cost.
     */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Whether the letter has reached a state nothing moves it out of.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Sent, self::Cancelled, self::Failed, self::Invalid], true);
    }
}
