<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth Client
    |--------------------------------------------------------------------------
    |
    | The client id and secret of your Pingen OAuth client. This package uses
    | the `client_credentials` grant, which is what an application sending its
    | own post needs — no user is involved.
    |
    */
    'client_id' => env('PINGEN_CLIENT_ID'),
    'client_secret' => env('PINGEN_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default Organisation
    |--------------------------------------------------------------------------
    |
    | Every Pingen endpoint is scoped to an organisation UUID. This is the one
    | used when no other is named. An application holding several accounts asks
    | for another with `PingenService::forOrganisation($uuid)` instead of
    | rebinding anything.
    |
    */
    'organisation_id' => env('PINGEN_ORGANISATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Pingen runs a separate staging environment with its own identity server,
    | its own API host and its own accounts. `staging` switches both hosts at
    | once; `base_url` and `identity_url` override them individually and are
    | only needed to point at something else entirely.
    |
    */
    'staging' => (bool) env('PINGEN_STAGING', false),
    'base_url' => env('PINGEN_BASE_URL'),
    'identity_url' => env('PINGEN_IDENTITY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | Requested with the access token, as a space-separated string. Null asks
    | for whatever the OAuth client was granted, which is usually what you
    | want: a scope the client does not hold makes the token request fail
    | rather than return a smaller token.
    |
    */
    'scopes' => env('PINGEN_SCOPES'),

    /*
    |--------------------------------------------------------------------------
    | Access Token Cache
    |--------------------------------------------------------------------------
    |
    | The token is fetched once and cached until shortly before it expires.
    | `margin` is how many seconds before the stated expiry it is thrown away,
    | so a request started just in time cannot arrive just too late.
    |
    | The cache key is suffixed with a hash of the client id and the host, so
    | staging and production — and two different clients — never share a token.
    |
    */
    'token_cache' => [
        'store' => env('PINGEN_TOKEN_CACHE_STORE'),
        'key' => env('PINGEN_TOKEN_CACHE_KEY', 'pingen:access-token'),
        'margin' => (int) env('PINGEN_TOKEN_MARGIN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Letter Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to a letter that does not name them itself.
    |
    | `auto_send` false creates the letter as a draft, which is then submitted
    | separately. That is the safer default: the price is known before anything
    | is posted, and a mistake can still be deleted.
    |
    | The three print options are deliberately plain strings rather than an
    | enum. The catalogue of products differs per country and changes without
    | this package being released, so they are validated against the allowlist
    | below and otherwise passed through untouched. An empty allowlist disables
    | the check for that option.
    |
    */
    'defaults' => [
        'address_position' => env('PINGEN_ADDRESS_POSITION', 'left'),
        'auto_send' => (bool) env('PINGEN_AUTO_SEND', false),
        'delivery_product' => env('PINGEN_DELIVERY_PRODUCT'),
        'print_mode' => env('PINGEN_PRINT_MODE'),
        'print_spectrum' => env('PINGEN_PRINT_SPECTRUM'),
    ],

    'allowed' => [
        'address_position' => ['left', 'right'],
        'delivery_product' => [],
        'print_mode' => [],
        'print_spectrum' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Pingen signs a delivery with a `Signature` header holding the hex
    | HMAC-SHA256 of the raw body under the webhook signing key. Nothing
    | time-bound is signed, so a captured delivery stays replayable: this
    | package therefore records every delivery id and ignores a repeat. That
    | guard lives in the `pingen_webhook_events` table, which means turning
    | `migrations` off below also turns the guard off.
    |
    | - enabled:     register the inbound route at all
    | - path:        where it listens
    | - signing_key: the key deliveries are verified against; empty skips
    |                verification, which is only ever right locally
    | - throttle:    rate limit as "attempts,minutes"; empty disables it
    |
    */
    'webhook' => [
        'enabled' => (bool) env('PINGEN_WEBHOOK_ENABLED', true),
        'path' => env('PINGEN_WEBHOOK_PATH', 'pingen/webhook'),
        'signing_key' => env('PINGEN_WEBHOOK_SIGNING_KEY'),
        'throttle' => env('PINGEN_WEBHOOK_THROTTLE', '120,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Whether this package's migrations run. An application that only wants the
    | API client can set this to false and inherit no tables — but see the note
    | above: the webhook replay guard is one of them.
    |
    */
    'migrations' => (bool) env('PINGEN_MIGRATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Storage / Logging
    |--------------------------------------------------------------------------
    |
    | - store_logs:     whether letters and webhook events are recorded at all
    | - storage_driver: "log" writes to the log channel, "database" to the
    |                   `pingen_letters` table
    |
    */
    'store_logs' => (bool) env('PINGEN_STORE_LOGS', false),
    'storage_driver' => env('PINGEN_STORAGE_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Payload Capture
    |--------------------------------------------------------------------------
    |
    | Directory to write every verified webhook body to, one JSON file per
    | delivery, for capturing real payloads as fixtures. Runs only in the local
    | and testing environments: a letter payload carries a recipient postal
    | address, and nothing prunes these files, so a path set on a production
    | system is ignored rather than obeyed.
    |
    */
    'capture_path' => env('PINGEN_CAPTURE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'times' => (int) env('PINGEN_RETRY_TIMES', 3),
        'delay' => (int) env('PINGEN_RETRY_DELAY_MS', 500),
    ],

];
