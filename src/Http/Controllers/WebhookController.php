<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Http\Controllers;

use GraystackIT\Pingen\Events\LetterCancelled;
use GraystackIT\Pingen\Events\LetterDelivered;
use GraystackIT\Pingen\Events\LetterFailed;
use GraystackIT\Pingen\Events\LetterSent;
use GraystackIT\Pingen\Events\LetterStatusChanged;
use GraystackIT\Pingen\Events\LetterUndeliverable;
use GraystackIT\Pingen\Models\PingenWebhookEvent;
use GraystackIT\Pingen\Support\LetterRecorder;
use GraystackIT\Pingen\Support\PayloadCapture;
use GraystackIT\Pingen\Traits\VerifiesPingenSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives Pingen webhook deliveries.
 *
 * Three things happen here, in this order and for a reason: the signature is
 * checked, the delivery is claimed, and only then is anything announced.
 * Claiming before dispatching is what stops a replayed or retried delivery
 * from firing the application's listeners twice — see
 * {@see VerifiesPingenSignature} for why a valid signature is not enough on
 * its own.
 *
 * An unrecognised status is not an error. Pingen extends its vocabulary
 * without this package being released, so anything unmapped is announced as
 * {@see LetterStatusChanged} carrying the raw string, and the application can
 * react to a status this version has never heard of.
 */
class WebhookController extends Controller
{
    use VerifiesPingenSignature;

    /**
     * Handle one delivery.
     *
     * @param  Request  $request  The inbound request.
     * @param  LetterRecorder  $recorder  Writes the status to the log or the database.
     */
    public function handle(Request $request, LetterRecorder $recorder): JsonResponse
    {
        if (! $this->signatureIsValid($request, config('pingen.webhook.signing_key'))) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->json()->all();

        /** @var array<string, mixed> $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        $eventId = (string) ($data['id'] ?? '');
        $letterId = $this->letterId($data);
        $status = (string) ($attributes['status'] ?? $attributes['code'] ?? '');
        $category = $this->stringOrNull($attributes['event_category'] ?? $data['type'] ?? null);

        PayloadCapture::write($request, $category ?? 'webhook');

        if (! $this->claim($eventId, $letterId, $category, $payload)) {
            Log::info('Pingen webhook: delivery already handled, ignoring', ['event_id' => $eventId]);

            return response()->json(['status' => 'ok', 'duplicate' => true]);
        }

        Log::info('Pingen webhook received', [
            'event_id' => $eventId,
            'letter_id' => $letterId,
            'status' => $status,
        ]);

        if ($letterId !== null && $status !== '') {
            $recorder->recordStatus($letterId, $status, $payload);
            $this->announce($letterId, $status, $payload);

            return response()->json(['status' => 'ok']);
        }

        // Verified, claimed, and about nothing this package can act on. That
        // deserves a line of its own: from the provider's side it looks exactly
        // like a delivery that was handled, and an endpoint answering 200 to
        // everything is the hardest kind of webhook to find a fault in.
        Log::notice('Pingen webhook: nothing to announce, the delivery names no letter or no status', [
            'event_id' => $eventId,
            'category' => $category,
            'letter_id' => $letterId,
            'status' => $status,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Claim the delivery, so a repeat of it does nothing.
     *
     * A delivery without an id cannot be claimed and is let through: refusing
     * it would drop real events if Pingen ever sends one unidentified, and
     * letting it through is the same behaviour as having no guard at all.
     * A missing table — the guard migration switched off — is also let
     * through, loudly.
     *
     * @param  string  $eventId  The delivery id.
     * @param  string|null  $letterId  UUID of the letter it concerns.
     * @param  string|null  $category  The event category.
     * @param  array<string, mixed>  $payload  The delivery body.
     */
    private function claim(string $eventId, ?string $letterId, ?string $category, array $payload): bool
    {
        if ($eventId === '') {
            return true;
        }

        try {
            return PingenWebhookEvent::claim($eventId, $letterId, $category, $payload);
        } catch (Throwable $exception) {
            Log::warning(
                'Pingen webhook: the replay guard is unavailable, processing the delivery unguarded',
                ['error' => $exception->getMessage()],
            );

            return true;
        }
    }

    /**
     * Fire the event that fits the reported status.
     *
     * @param  string  $letterId  UUID of the letter.
     * @param  string  $status  Status as reported.
     * @param  array<string, mixed>  $payload  The delivery body.
     */
    private function announce(string $letterId, string $status, array $payload): void
    {
        match ($status) {
            'sent' => LetterSent::dispatch($letterId, $status, $payload),
            'delivered' => LetterDelivered::dispatch($letterId, $status, $payload),
            'cancelled', 'canceled' => LetterCancelled::dispatch($letterId, $status, $payload),
            'failed', 'invalid' => LetterFailed::dispatch($letterId, $status, $payload),
            'undeliverable', 'returned' => LetterUndeliverable::dispatch($letterId, $status, $payload),
            default => LetterStatusChanged::dispatch($letterId, $status, $payload),
        };
    }

    /**
     * Read the letter id out of a delivery, wherever it sits.
     *
     * @param  array<string, mixed>  $data  The delivery's `data` object.
     */
    private function letterId(array $data): ?string
    {
        $relationship = $data['relationships']['letter']['data']['id'] ?? null;

        if (is_string($relationship) && $relationship !== '') {
            return $relationship;
        }

        $attribute = $data['attributes']['letter_id'] ?? null;

        return is_string($attribute) && $attribute !== '' ? $attribute : null;
    }

    /**
     * Read a value as a non-empty string, or null.
     *
     * @param  mixed  $value  The raw value.
     */
    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
