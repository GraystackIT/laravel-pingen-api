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
 * Cases marked "confirmed" were observed on a staging account. The rest are
 * reconstructed from the official SDK and the dashboard vocabulary rather than
 * from a published list, so confirm one before relying on it; the fallback
 * above is what makes that safe to do after release.
 */
enum LetterStatus: string
{
    /**
     * Created but not submitted. Costs nothing and can still be deleted.
     */
    case Draft = 'draft';

    /**
     * The uploaded file is being checked and rendered. Confirmed: this is the
     * status a letter is created with, before the inspection finishes.
     */
    case Validating = 'validating';

    /**
     * The file cleared inspection and the letter is ready to submit, but
     * nothing has been spent yet. Confirmed.
     *
     * This - not Draft - is what a letter created through the API settles on
     * once Pingen has read it, so an application deciding whether a letter can
     * still be changed must accept both.
     */
    case Valid = 'valid';

    /**
     * The inspection found something only a person can resolve - an address
     * that could not be read, a font that is not embedded - and the letter
     * waits rather than failing. Confirmed.
     *
     * Distinct from Invalid: this one can still be fixed and submitted.
     */
    case ActionRequired = 'action_required';

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
        return in_array($this, [self::Draft, self::Valid], true);
    }

    /**
     * Whether the letter is waiting on the sender before it can be submitted.
     */
    public function needsAttention(): bool
    {
        return $this === self::ActionRequired;
    }

    /**
     * Whether the letter has reached a state nothing moves it out of.
     */
    public function isFinal(): bool
    {
        // ActionRequired is deliberately absent: it looks like a dead end and
        // is not one, because fixing the file and submitting still works.
        return in_array($this, [self::Sent, self::Cancelled, self::Failed, self::Invalid], true);
    }
}
