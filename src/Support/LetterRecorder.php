<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Support;

use GraystackIT\Pingen\Data\Letter;
use GraystackIT\Pingen\Models\PingenLetter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a letter to wherever the configuration says letters go.
 *
 * Recording is optional and off by default, because an application that keeps
 * its own record of what it sent does not need a second one. Neither driver is
 * allowed to break a send: a letter that reached Pingen has been paid for, and
 * failing the call afterwards because a local write failed would turn a
 * bookkeeping problem into a lost letter and, worse, invite a retry that sends
 * it twice.
 */
class LetterRecorder
{
    /**
     * Record a letter, if recording is switched on.
     *
     * @param  Letter  $letter  The letter as the API returned it.
     * @param  string  $organisationId  UUID of the owning organisation.
     */
    public function record(Letter $letter, string $organisationId): void
    {
        if (! config('pingen.store_logs', false)) {
            return;
        }

        if (config('pingen.storage_driver') !== 'database') {
            Log::info('Pingen letter', [
                'letter_id' => $letter->id,
                'status' => $letter->rawStatus,
                'organisation_id' => $organisationId,
            ]);

            return;
        }

        try {
            PingenLetter::query()->updateOrCreate(
                ['letter_id' => $letter->id],
                [
                    'organisation_id' => $organisationId,
                    'file_original_name' => $letter->fileOriginalName,
                    'file_pages' => $letter->filePages,
                    'status' => $letter->rawStatus,
                    'country' => $letter->country,
                    'price_currency' => $letter->priceCurrency,
                    'price_value' => $letter->priceValue,
                    'tracking_number' => $letter->trackingNumber,
                    'submitted_at' => $letter->submittedAt,
                    'payload' => $letter->raw,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Pingen: could not record the letter locally', [
                'letter_id' => $letter->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Update a recorded letter's status from a webhook.
     *
     * @param  string  $letterId  UUID of the letter.
     * @param  string  $status  The new status, as reported.
     * @param  array<string, mixed>  $payload  The delivery body, stored for reference.
     */
    public function recordStatus(string $letterId, string $status, array $payload): void
    {
        if (! config('pingen.store_logs', false)) {
            return;
        }

        if (config('pingen.storage_driver') !== 'database') {
            Log::info('Pingen letter status', [
                'letter_id' => $letterId,
                'status' => $status,
            ]);

            return;
        }

        try {
            PingenLetter::query()
                ->where('letter_id', $letterId)
                ->update(['status' => $status, 'payload' => $payload]);
        } catch (Throwable $exception) {
            Log::warning('Pingen: could not record the letter status locally', [
                'letter_id' => $letterId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
