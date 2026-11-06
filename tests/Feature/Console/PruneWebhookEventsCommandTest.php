<?php

declare(strict_types=1);

use GraystackIT\Pingen\Models\PingenWebhookEvent;

/**
 * Create a delivery record that was written at the given age.
 *
 * `created_at` is not fillable, so it is forced after the fact rather than
 * passed in — otherwise every row is written as of now and a retention test
 * silently proves nothing.
 */
function agedDelivery(string $eventId, int $daysAgo): PingenWebhookEvent
{
    $event = PingenWebhookEvent::query()->create(['event_id' => $eventId]);

    $event->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

    return $event;
}

it('removes deliveries older than the retention window and keeps the rest', function (): void {
    agedDelivery('old', 45);
    agedDelivery('recent', 5);

    $this->artisan('pingen:prune-webhook-events')->assertExitCode(0);

    expect(PingenWebhookEvent::query()->pluck('event_id')->all())->toBe(['recent']);
});

it('honours a custom retention window', function (): void {
    agedDelivery('a', 5);

    $this->artisan('pingen:prune-webhook-events', ['--days' => 2])->assertExitCode(0);

    expect(PingenWebhookEvent::query()->count())->toBe(0);
});

it('claims a delivery once and refuses the second claim', function (): void {
    // The insert is the lock: two workers handling the same retry must not
    // both win, and checking-then-writing would let them.
    expect(PingenWebhookEvent::claim('e-1', 'l-1', 'letters', []))->toBeTrue()
        ->and(PingenWebhookEvent::claim('e-1', 'l-1', 'letters', []))->toBeFalse()
        ->and(PingenWebhookEvent::query()->count())->toBe(1);
});
