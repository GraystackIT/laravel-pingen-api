# graystackit/laravel-pingen-api

A Laravel package for the [Pingen API](https://pingen.com/), built on [Saloon 4](https://docs.saloon.dev/).

Upload a PDF and Pingen prints and posts it as a physical letter. Create drafts, price them before they cost anything, submit, cancel, track delivery events, and receive signed webhooks as Laravel events — all with a clean, Laravel-native interface.

## Requirements

- PHP 8.3+
- Laravel 10, 11, 12, or 13
- A Pingen account with an OAuth client (`client_credentials` grant)

## Installation

```bash
composer require graystackit/laravel-pingen-api
```

Laravel auto-discovers the service provider. Then publish the config file and, optionally, the migrations:

```bash
php artisan vendor:publish --tag=pingen-config
php artisan vendor:publish --tag=pingen-migrations   # optional, see "Tables" below
```

## Configuration

Add the following to your `.env` file:

```env
PINGEN_CLIENT_ID=your-oauth-client-id
PINGEN_CLIENT_SECRET=your-oauth-client-secret
PINGEN_ORGANISATION_ID=your-organisation-uuid

# Optional — defaults shown
PINGEN_STAGING=false
PINGEN_SCOPES=
PINGEN_ADDRESS_POSITION=left
PINGEN_AUTO_SEND=false
PINGEN_DELIVERY_PRODUCT=
PINGEN_PRINT_MODE=
PINGEN_PRINT_SPECTRUM=
PINGEN_WEBHOOK_ENABLED=true
PINGEN_WEBHOOK_PATH=pingen/webhook
PINGEN_WEBHOOK_SIGNING_KEY=
PINGEN_WEBHOOK_THROTTLE=120,1
PINGEN_MIGRATIONS=true
PINGEN_STORE_LOGS=false
PINGEN_STORAGE_DRIVER=log
PINGEN_RETRY_TIMES=3
PINGEN_RETRY_DELAY_MS=500
```

`PINGEN_STAGING=true` switches both the identity server and the API host at once — Pingen runs staging as a separate world with its own accounts and data. Keep it on until you mean to post something real.

If the OAuth client or the organisation is not configured, a `ConfigurationException` is thrown before any request is sent.

Don't know your organisation UUID yet?

```php
use GraystackIT\Pingen\PingenService;

foreach (PingenService::fromConfig()->organisations()->list() as $organisation) {
    echo $organisation->id.' '.$organisation->name.PHP_EOL;
}
```

## Usage

Inject `PingenService` via the constructor or resolve it from the container.

### Send a letter

```php
use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\PingenService;

$letter = app(PingenService::class)->send(new LetterDraft(
    contents: $pdfBytes,
    fileName: 'invoice-2026-0042.pdf',
    metaData: ['invoice_id' => $invoice->id],
));

$letter->id;      // the UUID Pingen assigned
$letter->status;  // LetterStatus::Draft
```

That one call does three HTTP requests: it asks for an upload slot, PUTs the file to the signed URL it gets back, and creates the letter quoting that slot's signature.

### Send a file from disk

```php
$letter = app(PingenService::class)->send(
    LetterDraft::fromPath(storage_path('invoices/inv-42.pdf'))
);
```

### Price first, then submit

`auto_send` defaults to **false**, so `send()` creates a draft. Nothing is printed and nothing is charged until it is submitted:

```php
$price = $service->estimate(country: 'AT');   // 1.65 CHF

$service->submit($letter->id);                // now it costs money
```

Set `PINGEN_AUTO_SEND=true`, or pass `autoSend: true`, to do both in one call. Auto-sending requires `deliveryProduct`, `printMode` and `printSpectrum` — from the draft or the configured defaults — because there is no later call in which to name them.

### Using dependency injection

```php
use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\PingenService;

class InvoiceController extends Controller
{
    public function __construct(private PingenService $pingen) {}

    public function post(Invoice $invoice): void
    {
        $this->pingen->send(new LetterDraft(
            contents: $invoice->renderPdf(),
            fileName: "invoice-{$invoice->number}.pdf",
        ));
    }
}
```

### Several Pingen accounts

The configured organisation is the default. Ask for another and you get a **separate** service rather than a mutated one, so a letter cannot leave from the wrong account by accident:

```php
$other = app(PingenService::class)->forOrganisation('another-organisation-uuid');
```

---

## Letter Management

```php
$service->find($letterId);                    // read one letter
$service->cancel($letterId);                  // stop it before printing
$service->letters()->list(limit: 50);         // paginated
$service->letters()->update($id, [...]);      // edit a draft
$service->letters()->delete($id);             // delete a draft
$service->letters()->downloadFile($id);       // the PDF as it will be printed
$service->events()->list($letterId);          // tracking history
```

---

## PDF Requirements

Pingen inspects the PDF before anything is printed. A file that does not pass the inspection doesn't fail outright: it lands on `LetterStatus::ActionRequired` and waits for a corrected file. **Fonts must be embedded**; a PDF relying on the viewer's own Helvetica is rejected.

### The address goes on the page

Pingen reads the recipient off the PDF through the address window. The package only says **where** that window is (`addressPosition`, `left` or `right`); putting the address there is the caller's job. There is no recipient argument, by design.

### Layout (A4, left address)

| Region | x | y | w | h |
|---|---|---|---|---|
| Restricted border | 5mm on every side | | | |
| Postage area | 20mm | 40mm | 89.5mm | 47.5mm |
| Address area | 22mm | 60mm | 85.5mm | 25.5mm |

The address area sits **inside** the postage area, which is the trap: an address block starting above y=60 is still inside the postage area and is rejected as content found there, while one starting lower overflows past y=85.5 and silently loses its first lines. The whole address has 25.5mm of height.

### Why a letter was rejected

The reason is in the event's `data`, and nowhere else:

```php
foreach ($service->events()->list($letterId) as $event) {
    echo $event->code;   // 'content_failed_inspection'
    print_r($event->data);   // ['protected_stamp_area'] — the actual reason
}
```

| Reason | Meaning |
|---|---|
| `protected_stamp_area` | Content found in the postage area |
| `address_insufficient_lines` | Too few address lines read in the window |
| `address_empty` | Nothing found in the address window |

---

## Print Options

`delivery_product`, `print_mode` and `print_spectrum` are passed through as plain strings rather than modelled as enums: the catalogue differs per country and changes without this package being released, and a hard-coded list would eventually block a product that works.

To constrain them for your own application, fill the allowlists in `config/pingen.php`:

```php
'allowed' => [
    'delivery_product' => ['cheap', 'fast'],
    'print_mode'       => ['simplex', 'duplex'],
    'print_spectrum'   => ['grayscale', 'color'],
],
```

An empty list means anything goes. A value outside a non-empty list throws `ConfigurationException` **before** the file is uploaded, so a typo costs nothing.

---

## Webhooks

Register one per event category. The categories are `issues`, `sent`, `undeliverable`, `delivered` and `channel_subscriptions` — there is no `letters`, and anything else is refused with a 422. A letter-sending application wants the first four.

The signing key is yours: Pingen never returns it, so store it before the first delivery arrives.

```bash
php artisan pingen:webhooks                                  # what is registered
php artisan pingen:webhooks --url=https://app.test/pingen/webhook
```

With no `--url` the command lists the registrations and says, per row, whether the key each was registered with still matches `pingen.webhook.signing_key` — compared, never printed. With a `--url` it replaces them all, which is what a development tunnel that renamed itself needs; pass `--keep` to add rather than replace, and `--category=` to choose.

Both failures this guards against are silent ones. A webhook pointing at a host that no longer answers, and a webhook registered under a key the application no longer has, look identical from the inside: no deliveries, no errors, nothing in any log.

In code:

```php
$service->webhooks()->create(
    eventCategory: 'sent',
    url:           route('pingen.webhook'),
    signingKey:    config('pingen.webhook.signing_key'),
);
```

The package registers `POST /pingen/webhook` and dispatches a Laravel event per delivery:

| Event | When |
|---|---|
| `LetterCreated` | A letter was created (fired locally, not by a webhook) |
| `LetterSubmitted` | A letter went to production |
| `LetterSent` | Handed to the postal service |
| `LetterDelivered` | Reported delivered |
| `LetterCancelled` | Stopped before printing |
| `LetterFailed` | Will not be sent |
| `LetterUndeliverable` | Could not be delivered — the copy was still produced and paid for |
| `LetterStatusChanged` | Any status this version does not model, carrying the raw string |

That last row matters: Pingen extends its vocabulary without this package being released, so an unrecognised status is announced rather than dropped.

### Signature and replay protection

Pingen signs a delivery as `Signature: hash_hmac('sha256', $rawBody, $signingKey)` in plain hex. **Nothing time-bound is signed**, so a signature never expires and a delivery captured off the wire stays replayable forever. Verifying it proves the body came from Pingen at *some* point, not that it is arriving for the first time.

The `pingen_webhook_events` table closes that gap: every delivery id is recorded, and a repeat is answered `{"status":"ok","duplicate":true}` without firing anything. It also makes Pingen's own retries harmless. **Setting `PINGEN_MIGRATIONS=false` removes that guard.**

Prune the table on a schedule:

```bash
php artisan pingen:prune-webhook-events --days=30
```

---

## Tables

| Table | Purpose |
|---|---|
| `pingen_webhook_events` | The replay guard above |
| `pingen_letters` | Optional record of letters sent, only with `PINGEN_STORE_LOGS=true` and `PINGEN_STORAGE_DRIVER=database` |

Neither is read to make a decision — the first is a guard, the second is a record. Set `PINGEN_MIGRATIONS=false` to inherit no tables at all, and read the warning above first.

---

## Error handling

All API errors throw `GraystackIT\Pingen\Exceptions\PingenException` (extends `RuntimeException`) or one of its subclasses.

```php
use GraystackIT\Pingen\Exceptions\LetterRejectedException;
use GraystackIT\Pingen\Exceptions\PingenException;
use GraystackIT\Pingen\Exceptions\RateLimitExceededException;

try {
    $letter = $service->send($draft);
} catch (LetterRejectedException $e) {
    // 400/409/422 — fix the input, do not retry
    print_r($e->errors);         // JSON:API error objects
} catch (RateLimitExceededException $e) {
    echo $e->retryAfter;         // seconds to wait
} catch (PingenException $e) {
    echo $e->getMessage();
    echo $e->status;             // HTTP status code
}
```

| Exception | Meaning |
|---|---|
| `ConfigurationException` | Missing credentials, or an option outside the allowlist — thrown before anything is sent |
| `LetterRejectedException` | Pingen refused the input (400/409/422): fix it, do not retry |
| `RateLimitExceededException` | 429, carrying `retryAfter`, `limit`, `remaining` and `resetsAt` |
| `FileUploadExpiredException` | The signed upload slot expired before the bytes were sent — request a new one |
| `PingenException` | Everything else; `->status` and `->errors` carry the JSON:API error objects |

The distinction is not cosmetic: an application that meters sends reacts in opposite ways to a 422 and a 429, and that decision should not be a string match on a message.

### Access tokens

Fetched with the `client_credentials` grant and cached until shortly before they expire. The cache key includes the client id and the identity host, so staging and production — and two OAuth clients — never share a token. A 401 against a cached token forgets it and retries once.

---

## Testing

Tests use Saloon's `MockClient` — no real API calls are made. The access token comes from Laravel's HTTP client, so fake the identity server alongside it:

```php
use GraystackIT\Pingen\PingenService;
use GraystackIT\Pingen\Requests\Letters\GetLetterRequest;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

Http::fake([
    'identity-staging.pingen.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
]);

$service = PingenService::fromConfig();

$service->connector()->withMockClient(new MockClient([
    GetLetterRequest::class => MockResponse::make([
        'data' => [
            'id' => 'letter-uuid',
            'type' => 'letters',
            'attributes' => ['status' => 'sent'],
        ],
    ], 200),
]));

$letter = $service->find('letter-uuid');
```

Run the package test suite:

```bash
vendor/bin/pest
```

Fixtures are invented throughout: a genuine Pingen payload carries a recipient's postal address, which is personal data and does not belong in a public repository.

### Against a real account

Install the package into a throwaway Laravel application, point it at a **staging** account, and drive it from `php artisan tinker`:

```bash
composer create-project laravel/laravel pingen-sandbox
cd pingen-sandbox
composer config repositories.pingen path ../laravel-pingen-api
composer require graystackit/laravel-pingen-api:@dev
```

A `path` repository symlinks the package, so edits to it are live in the sandbox with no reinstall. Work up from the reads — nothing costs money until `submit()`.

## A caveat on completeness

Pingen's documentation site renders in the browser and serves no machine-readable spec, so this package's endpoints and field names were reconstructed from the official [PHP SDK](https://github.com/pingencom/pingen2-sdk-php), then checked against a staging account.

Confirmed live: the three-call send flow, the price calculator, the letter and event reads, the print options `cheap` / `simplex` / `grayscale`, and the statuses `validating`, `valid` and `action_required`.

Still unconfirmed: the remaining `LetterStatus` cases, the `event_category` names used when registering a webhook, and the rest of the print-option catalogue. That is why unknown statuses degrade to `LetterStatus::Unknown` with the raw string kept beside them, and why the print options are plain strings with an optional allowlist. Please open an issue if you find one that differs.

## License

MIT.
