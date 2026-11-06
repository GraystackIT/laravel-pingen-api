<?php

/**
 * Live probe against a real Pingen account.
 *
 * The test suite proves the package against mocks; this script proves it
 * against the API itself — which is the only thing that can confirm the
 * values reconstructed from the SDK (letter statuses, event categories,
 * the print-option catalogues).
 *
 * It boots a throwaway Laravel application through Testbench, so nothing
 * here needs a host app.
 *
 * Usage:
 *   php scripts/probe.php token
 *   php scripts/probe.php organisations
 *   php scripts/probe.php price [country]
 *   php scripts/probe.php send <path-to.pdf>      # creates a DRAFT, costs nothing
 *   php scripts/probe.php find <letter-id>
 *   php scripts/probe.php events <letter-id>
 *   php scripts/probe.php submit <letter-id>      # COSTS MONEY — prints and posts
 *   php scripts/probe.php cancel <letter-id>
 *   php scripts/probe.php webhooks
 *   php scripts/probe.php webhook:create <url>
 *
 * Credentials come from a .env file in the package root (git-ignored):
 *
 *   PINGEN_CLIENT_ID=...
 *   PINGEN_CLIENT_SECRET=...
 *   PINGEN_ORGANISATION_ID=...
 *   PINGEN_STAGING=true
 */

declare(strict_types=1);

use GraystackIT\Pingen\Data\LetterDraft;
use GraystackIT\Pingen\PingenService;
use Orchestra\Testbench\Foundation\Application as Testbench;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

/**
 * Read KEY=VALUE pairs out of the package-root .env.
 *
 * @return array<string, string>
 */
$readEnv = static function (string $path): array {
    if (! is_file($path)) {
        fwrite(STDERR, "No .env found at {$path}.\n".
            "Create one with PINGEN_CLIENT_ID, PINGEN_CLIENT_SECRET and PINGEN_ORGANISATION_ID.\n");
        exit(1);
    }

    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $values[trim($key)] = trim(trim($value), "\"'");
    }

    return $values;
};

$env = $readEnv($root.'/.env');

foreach (['PINGEN_CLIENT_ID', 'PINGEN_CLIENT_SECRET', 'PINGEN_ORGANISATION_ID'] as $required) {
    if (empty($env[$required])) {
        fwrite(STDERR, "Missing {$required} in .env\n");
        exit(1);
    }
}

$staging = ! in_array(strtolower($env['PINGEN_STAGING'] ?? 'true'), ['false', '0', ''], true);

$app = Testbench::create(
    basePath: $root.'/vendor/orchestra/testbench-core/laravel',
    options: [
        'extra' => ['providers' => [GraystackIT\Pingen\PingenServiceProvider::class]],
        'enables_package_discoveries' => false,
    ],
);

// Set after the application is created: the resolving callback runs before the
// configuration repository exists. Everything the package reads is resolved
// lazily, so this still lands before the first request.
$app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
$app['config']->set('cache.default', 'array');

$app['config']->set('pingen.client_id', $env['PINGEN_CLIENT_ID']);
$app['config']->set('pingen.client_secret', $env['PINGEN_CLIENT_SECRET']);
$app['config']->set('pingen.organisation_id', $env['PINGEN_ORGANISATION_ID']);
$app['config']->set('pingen.staging', $staging);
$app['config']->set('pingen.webhook.signing_key', $env['PINGEN_WEBHOOK_SIGNING_KEY'] ?? null);

// Record every exchange so the real shapes can be compared against the ones
// this package assumes.
$app['config']->set('pingen.capture_path', $root.'/storage/probe');

/**
 * Print a value as readable JSON.
 */
$out = static function (mixed $value): void {
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
};

$service = $app->make(PingenService::class);
$command = $argv[1] ?? 'token';
$argument = $argv[2] ?? null;

echo ($staging ? '[staging] ' : '[PRODUCTION] ').$command.PHP_EOL.PHP_EOL;

try {
    switch ($command) {
        case 'token':
            $token = $app->make(GraystackIT\Pingen\Auth\AccessTokenStore::class)->token();

            $out(['token_prefix' => substr($token, 0, 12).'…', 'length' => strlen($token)]);
            break;

        case 'organisations':
            $out(array_map(
                static fn ($organisation) => ['id' => $organisation->id, 'name' => $organisation->name],
                $service->organisations()->list(),
            ));
            break;

        case 'price':
            $out($service->estimate(country: $argument ?? 'CH'));
            break;

        case 'send':
            if ($argument === null || ! is_file($argument)) {
                fwrite(STDERR, "Pass a readable PDF path.\n");
                exit(1);
            }

            $letter = $service->send(LetterDraft::fromPath($argument));

            $out(['id' => $letter->id, 'status' => $letter->status->value, 'raw_status' => $letter->rawStatus]);
            break;

        case 'find':
            $letter = $service->find((string) $argument);

            $out(['id' => $letter->id, 'status' => $letter->status->value, 'raw_status' => $letter->rawStatus, 'raw' => $letter->raw]);
            break;

        case 'events':
            $out(array_map(
                static fn ($event) => ['code' => $event->code, 'name' => $event->name, 'emitted_at' => $event->emittedAt?->toIso8601String()],
                $service->events()->list((string) $argument),
            ));
            break;

        case 'submit':
            $letter = $service->submit((string) $argument);

            $out(['id' => $letter->id, 'status' => $letter->status->value, 'raw_status' => $letter->rawStatus]);
            break;

        case 'cancel':
            $service->cancel((string) $argument);

            $out(['cancelled' => $argument]);
            break;

        case 'webhooks':
            $out(array_map(
                static fn ($webhook) => ['id' => $webhook->id, 'category' => $webhook->eventCategory, 'url' => $webhook->url],
                $service->webhooks()->list(),
            ));
            break;

        case 'webhook:create':
            $out($service->webhooks()->create('letters', (string) $argument));
            break;

        default:
            fwrite(STDERR, "Unknown command '{$command}'. See the header of this file.\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);

    if (property_exists($e, 'errors') && $e->errors) {
        fwrite(STDERR, json_encode($e->errors, JSON_PRETTY_PRINT).PHP_EOL);
    }

    exit(1);
}
