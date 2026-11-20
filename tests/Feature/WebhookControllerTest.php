<?php

declare(strict_types=1);

use GraystackIT\Pingen\Events\LetterCancelled;
use GraystackIT\Pingen\Events\LetterDelivered;
use GraystackIT\Pingen\Events\LetterFailed;
use GraystackIT\Pingen\Events\LetterSent;
use GraystackIT\Pingen\Events\LetterStatusChanged;
use GraystackIT\Pingen\Events\LetterUndeliverable;
use GraystackIT\Pingen\Models\PingenLetter;
use GraystackIT\Pingen\Models\PingenWebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Post a delivery to the webhook endpoint, signed if a key is configured.
 *
 * @param  array<string, mixed>  $payload  The delivery body.
 */
function postWebhook(array $payload, ?string $signWith = null): \Illuminate\Testing\TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $headers = $signWith === null ? [] : ['Signature' => pingenSignature($body, $signWith)];

    return test()->call(
        'POST',
        '/pingen/webhook',
        [],
        [],
        [],
        array_merge(['CONTENT_TYPE' => 'application/json'], collect($headers)
            ->mapWithKeys(fn (string $v, string $k): array => ['HTTP_'.strtoupper($k) => $v])
            ->all()),
        $body,
    );
}

it('accepts a correctly signed delivery', function (): void {
    config()->set('pingen.webhook.signing_key', 'super-secret');

    postWebhook(webhookPayload(), 'super-secret')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('refuses a delivery signed with the wrong key', function (): void {
    config()->set('pingen.webhook.signing_key', 'super-secret');

    postWebhook(webhookPayload(), 'the-wrong-key')
        ->assertStatus(401)
        ->assertJson(['error' => 'Invalid signature']);
});

it('refuses a delivery with no signature at all', function (): void {
    config()->set('pingen.webhook.signing_key', 'super-secret');

    postWebhook(webhookPayload())->assertStatus(401);
});

it('skips verification when no signing key is configured', function (): void {
    // Only ever right on a local machine, and the config says so.
    config()->set('pingen.webhook.signing_key', null);

    postWebhook(webhookPayload())->assertOk();
});

it('ignores a replayed delivery', function (): void {
    // The signature has no timestamp in it, so a captured delivery stays valid
    // forever. The delivery id is the only thing standing between that and the
    // application processing it again.
    Event::fake([LetterSent::class]);

    postWebhook(webhookPayload(eventId: 'event-7'))->assertOk();
    postWebhook(webhookPayload(eventId: 'event-7'))
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    Event::assertDispatchedTimes(LetterSent::class, 1);
    expect(PingenWebhookEvent::query()->count())->toBe(1);
});

it('treats two different deliveries as two', function (): void {
    Event::fake([LetterSent::class]);

    postWebhook(webhookPayload(eventId: 'event-1'))->assertOk();
    postWebhook(webhookPayload(eventId: 'event-2'))->assertOk();

    Event::assertDispatchedTimes(LetterSent::class, 2);
});

it('announces the event that fits the status', function (string $status, string $event): void {
    Event::fake([$event]);

    postWebhook(webhookPayload(status: $status, eventId: 'event-'.$status))->assertOk();

    Event::assertDispatched($event);
})->with([
    ['sent', LetterSent::class],
    ['delivered', LetterDelivered::class],
    ['cancelled', LetterCancelled::class],
    ['failed', LetterFailed::class],
    ['undeliverable', LetterUndeliverable::class],
]);

it('announces an unknown status as a status change rather than dropping it', function (): void {
    // Pingen extends its vocabulary without this package being released. An
    // application must still be able to react to something newer than this.
    Event::fake([LetterStatusChanged::class]);

    postWebhook(webhookPayload(status: 'held_at_customs'))->assertOk();

    Event::assertDispatched(
        LetterStatusChanged::class,
        fn (LetterStatusChanged $event): bool => $event->status === 'held_at_customs'
    );
});

it('reads the letter id out of the relationship', function (): void {
    Event::fake([LetterSent::class]);

    postWebhook(webhookPayload(letterId: 'letter-xyz'))->assertOk();

    Event::assertDispatched(
        LetterSent::class,
        fn (LetterSent $event): bool => $event->letterId === 'letter-xyz'
    );
});

it('updates the stored letter when database storage is on', function (): void {
    config()->set('pingen.store_logs', true);
    config()->set('pingen.storage_driver', 'database');

    PingenLetter::query()->create([
        'letter_id' => '11111111-2222-4333-8444-555555555555',
        'organisation_id' => '00000000-0000-4000-8000-000000000001',
        'file_original_name' => 'invoice.pdf',
        'status' => 'sending',
    ]);

    postWebhook(webhookPayload(status: 'delivered'))->assertOk();

    expect(PingenLetter::query()->firstOrFail()->status)->toBe('delivered');
});

it('is not registered when webhooks are switched off', function (): void {
    // The route is loaded by the service provider only when enabled, so an
    // application that wants to route deliveries itself can turn it off.
    expect(config('pingen.webhook.enabled'))->toBeTrue();

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route): string => $route->uri());

    expect($routes)->toContain('pingen/webhook');
});

it('says so in the log when a delivery names no status to act on', function (): void {
    // A verified, claimed delivery that this package can do nothing with still
    // answers 200, which from the provider side is indistinguishable from one
    // that was handled. The log line is the only way to tell the two apart.
    Log::spy();
    Event::fake([LetterSent::class, LetterStatusChanged::class]);

    postWebhook(webhookPayload(status: ''))->assertOk();

    Event::assertNotDispatched(LetterSent::class);
    Event::assertNotDispatched(LetterStatusChanged::class);
    Log::shouldHaveReceived('notice')->once();
});
