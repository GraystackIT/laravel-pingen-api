# laravel-pingen-api

A Laravel package for the [Pingen](https://pingen.com) print-and-post API — upload a PDF, and it is
printed and posted as a physical letter. Built on **Saloon v4**.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13
- A Pingen account with an OAuth client (`client_credentials` grant)

## Installation

```bash
composer require graystackit/laravel-pingen-api
```

The service provider is auto-discovered.

```bash
php artisan vendor:publish --tag=pingen-config      # optional
php artisan vendor:publish --tag=pingen-migrations  # optional, see "Tables" below
```

## Configuration

```dotenv
PINGEN_CLIENT_ID=your-oauth-client-id
PINGEN_CLIENT_SECRET=your-oauth-client-secret
PINGEN_ORGANISATION_ID=your-organisation-uuid
PINGEN_STAGING=true

# Optional
PINGEN_SCOPES=
PINGEN_ADDRESS_POSITION=left
PINGEN_AUTO_SEND=false
PINGEN_DELIVERY_PRODUCT=
PINGEN_PRINT_MODE=
PINGEN_PRINT_SPECTRUM=
PINGEN_WEBHOOK_ENABLED=true
PINGEN_WEBHOOK_PATH=pingen/webhook
PINGEN_WEBHOOK_SIGNING_KEY=
PINGEN_MIGRATIONS=true
PINGEN_STORE_LOGS=false
PINGEN_STORAGE_DRIVER=log
```

`PINGEN_STAGING` switches both the identity server and the API host at once — Pingen runs staging
as a separate world with its own accounts and data.

Don't know your organisation UUID yet:

```php
foreach (PingenService::fromConfig()->organisations()->list() as $organisation) {
    echo $organisation->id.' '.$organisation->name.PHP_EOL;
}
```

## Sending a letter

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

That one call does three HTTP requests: it asks for an upload slot, PUTs the file to the signed URL
it gets back, and creates the letter quoting that slot's signature.

From a file on disk:

```php
$letter = app(PingenService::class)->send(
    LetterDraft::fromPath(storage_path('invoices/inv-42.pdf'))
);
```

### The address goes on the page

Pingen reads the recipient off the PDF through the address window. The package only says **where**
that window is (`addressPosition`, `left` or `right`); putting the address there is the caller's
job. There is no recipient argument, by design.

### Drafts, by default

`auto_send` defaults to **false**, so `send()` creates a draft. Nothing is printed and nothing is
charged until it is submitted, which means you can show a price first:

```php
$price = $service->estimate(country: 'AT');   // 1.65 CHF

$service->submit($letter->id);                // now it costs money
```

Set `PINGEN_AUTO_SEND=true`, or pass `autoSend: true`, to do both in one call. Auto-sending
requires `deliveryProduct`, `printMode` and `printSpectrum` — from the draft or the configured
defaults — because there is no later call in which to name them.

### Everything else

```php
$service->find($letterId);                    // read one letter
$service->cancel($letterId);                  // stop it before printing
$service->letters()->list(limit: 50);         // paginated
$service->letters()->update($id, [...]);      // edit a draft
$service->letters()->delete($id);             // delete a draft
$service->letters()->downloadFile($id);       // the PDF as it will be printed
$service->events()->list($letterId);          // tracking history
```

### Several Pingen accounts

The configured organisation is the default; ask for another and you get a **separate** service
rather than a mutated one, so a letter cannot leave from the wrong account by accident:

```php
$other = app(PingenService::class)->forOrganisation('another-organisation-uuid');
```

## Print options

`delivery_product`, `print_mode` and `print_spectrum` are passed through as plain strings rather
than modelled as enums: the catalogue differs per country and changes without this package being
released, and a hard-coded list would eventually block a product that works.

To constrain them for your own application, fill the allowlists in `config/pingen.php`:

```php
'allowed' => [
    'delivery_product' => ['cheap', 'fast'],
    'print_mode'       => ['simplex', 'duplex'],
    'print_spectrum'   => ['grayscale', 'color'],
],
```

An empty list means anything goes. A value outside a non-empty list throws `ConfigurationException`
**before** the file is uploaded, so a typo costs nothing.

## Webhooks

Register one per event category. The signing key is yours — Pingen never returns it, so store it
before the first delivery arrives:

```php
$service->webhooks()->create(
    eventCategory: 'letters',
    url: route('pingen.webhook'),
    signingKey: config('pingen.webhook.signing_key'),
);
```

The package registers `POST /pingen/webhook` and dispatches a Laravel event per delivery:

| Event | When |
|---|---|
| `LetterCreated` | a letter was created (fired locally, not by a webhook) |
| `LetterSubmitted` | a letter went to production |
| `LetterSent` | handed to the postal service |
| `LetterDelivered` | reported delivered |
| `LetterCancelled` | stopped before printing |
| `LetterFailed` | will not be sent |
| `LetterUndeliverable` | could not be delivered — note the copy was still produced and paid for |
| `LetterStatusChanged` | any status this version does not model, carrying the raw string |

That last row matters: Pingen extends its vocabulary without this package being released, so an
unrecognised status is announced rather than dropped.

### A note on the signature, worth reading

Pingen signs a delivery as `Signature: hash_hmac('sha256', $rawBody, $signingKey)` in plain hex.
**Nothing time-bound is signed** — there is no timestamp inside the signed content — so a signature
never expires, and a delivery captured off the wire stays replayable forever. Verifying it proves
the body came from Pingen at *some* point, not that it is arriving for the first time.

What closes that is the `pingen_webhook_events` table: every delivery id is recorded, and a repeat
is answered `{"status":"ok","duplicate":true}` without firing anything. It also makes Pingen's own
retries harmless. **Switching `PINGEN_MIGRATIONS` off removes that guard.**

Prune the table on a schedule:

```bash
php artisan pingen:prune-webhook-events --days=30
```

## Tables

| Table | Purpose |
|---|---|
| `pingen_webhook_events` | the replay guard above |
| `pingen_letters` | optional record of letters sent, only with `PINGEN_STORE_LOGS=true` and `PINGEN_STORAGE_DRIVER=database` |

Neither is read to make a decision — the first is a guard, the second is a record. Set
`PINGEN_MIGRATIONS=false` to inherit no tables at all, and read the warning above first.

## Errors

| Exception | Means |
|---|---|
| `ConfigurationException` | missing credentials, or an option outside the allowlist — thrown before anything is sent |
| `LetterRejectedException` | Pingen refused the input (400/409/422): fix it, do not retry |
| `RateLimitExceededException` | 429, carrying `retryAfter`, `limit`, `remaining` and `resetsAt` |
| `FileUploadExpiredException` | the signed upload slot expired before the bytes were sent — request a new one |
| `PingenException` | everything else; `->status` and `->errors` carry the JSON:API error objects |

The distinction is not cosmetic: an application that meters sends reacts in opposite ways to a 422
and a 429, and that decision should not be a string match on a message.

## Access tokens

Fetched with the `client_credentials` grant and cached until shortly before they expire. The cache
key includes the client id and the identity host, so staging and production — and two OAuth
clients — never share a token. A 401 against a cached token forgets it and retries once.

## Testing

```bash
composer install
vendor/bin/pest
```

Every test uses Saloon's `MockClient` and reaches nothing real. Fixtures are invented throughout:
a genuine Pingen payload carries a recipient's postal address, which is personal data and does not
belong in a public repository.

## A caveat on completeness

Pingen's documentation site renders in the browser and serves no machine-readable spec, so this
package's endpoints and field names were reconstructed from the official
[PHP SDK](https://github.com/pingencom/pingen2-sdk-php). Paths, request shapes and the webhook
signature are reliable. The specific values of `LetterStatus`, the `event_category` names and the
print-option catalogues are **not yet confirmed against a live account** — which is why unknown
statuses degrade gracefully and the print options are strings with an optional allowlist. Please
open an issue if you find one that differs.

## License

MIT. See [LICENSE](LICENSE).
