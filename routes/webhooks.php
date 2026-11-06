<?php

declare(strict_types=1);

use GraystackIT\Pingen\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pingen Webhook Route
|--------------------------------------------------------------------------
|
| One public endpoint, registered by the service provider only when
| `pingen.webhook.enabled` is true — an application that wants to route
| deliveries itself turns it off and points its own route at the controller.
|
| Being public, it carries a rate limit by default. Set PINGEN_WEBHOOK_THROTTLE
| empty to remove it.
|
*/

$throttle = trim((string) config('pingen.webhook.throttle', ''));
$middleware = $throttle === '' ? [] : ["throttle:{$throttle}"];

Route::middleware($middleware)->post(
    (string) config('pingen.webhook.path', 'pingen/webhook'),
    [WebhookController::class, 'handle'],
)->name('pingen.webhook');
