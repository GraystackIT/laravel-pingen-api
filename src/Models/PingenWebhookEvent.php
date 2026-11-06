<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * A webhook delivery that has already been handled.
 *
 * The unique `event_id` is the whole point: it is what makes a replayed or
 * retried delivery a no-op. See the migration for why that guard is needed at
 * all.
 *
 * @property int $id
 * @property string $event_id
 * @property string|null $letter_id
 * @property string|null $category
 * @property array<string, mixed>|null $payload
 * @property CarbonInterface|null $processed_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class PingenWebhookEvent extends Model
{
    protected $table = 'pingen_webhook_events';

    protected $fillable = [
        'event_id',
        'letter_id',
        'category',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Claim a delivery, or report that it was already handled.
     *
     * The insert itself is the lock. Checking for an existing row and then
     * writing one would leave a window in which two workers both find nothing
     * and both process the delivery — which is exactly the case this guard
     * exists for, since Pingen retries in parallel with its own timeouts.
     *
     * @param  string  $eventId  The delivery id from the payload.
     * @param  string|null  $letterId  UUID of the letter it concerns.
     * @param  string|null  $category  The event category.
     * @param  array<string, mixed>  $payload  The delivery body.
     * @return bool True when this call claimed it, false when it was already taken.
     */
    public static function claim(string $eventId, ?string $letterId, ?string $category, array $payload): bool
    {
        try {
            static::query()->create([
                'event_id' => $eventId,
                'letter_id' => $letterId,
                'category' => $category,
                'payload' => $payload,
                'processed_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
