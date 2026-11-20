<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Console;

use GraystackIT\Pingen\PingenService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Lists the registered webhooks, or points them at an endpoint.
 *
 * Registration is a thing an application does rarely and under pressure —
 * usually because deliveries have stopped arriving — and the two ways it
 * commonly goes wrong are both silent. A development tunnel renames itself on
 * restart and the provider carries on posting to a host that no longer answers;
 * and a webhook registered with a signing key other than the configured one
 * fails verification forever, with nothing on the provider's side to show for
 * it. So this command takes no key at all, and prints what is registered after
 * every change.
 */
class WebhooksCommand extends Command
{
    /**
     * The categories a letter-sending application wants.
     *
     * `channel_subscriptions` is deliberately not among them: it concerns
     * inbound channels rather than letters, and an application that wants it
     * names it explicitly.
     *
     * @var list<string>
     */
    private const DEFAULT_CATEGORIES = ['issues', 'sent', 'undeliverable', 'delivered'];

    protected $signature = 'pingen:webhooks
        {--url= : Register the webhooks against this endpoint}
        {--category=* : Event categories to register; defaults to issues, sent, undeliverable and delivered}
        {--keep : Leave the existing registrations in place instead of replacing them}';

    protected $description = 'List the registered Pingen webhooks, or register them against an endpoint';

    /**
     * Run the command.
     */
    public function handle(PingenService $pingen): int
    {
        $webhooks = $pingen->webhooks();
        $url = trim((string) $this->option('url'));

        try {
            if ($url === '') {
                return $this->show($webhooks->list());
            }

            if (! $this->option('keep')) {
                foreach ($webhooks->list() as $existing) {
                    $webhooks->delete($existing->id);
                    $this->line("  removed {$existing->eventCategory} -> {$existing->url}");
                }
            }

            /** @var list<string> $categories */
            $categories = (array) $this->option('category');
            $categories = $categories === [] ? self::DEFAULT_CATEGORIES : $categories;

            foreach ($categories as $category) {
                $webhooks->create($category, $url);
            }

            return $this->show($webhooks->list());
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Print what is registered, and whether it can be verified.
     *
     * The signing key is compared, never printed: a key read off a terminal is
     * a key in a scrollback buffer.
     *
     * @param  array<int, \GraystackIT\Pingen\Data\Webhook>  $webhooks  What the API reports.
     */
    private function show(array $webhooks): int
    {
        if ($webhooks === []) {
            $this->warn('No webhooks are registered.');

            return self::SUCCESS;
        }

        $configured = (string) config('pingen.webhook.signing_key', '');

        $this->table(
            ['Category', 'URL', 'Key matches config'],
            array_map(static fn ($webhook): array => [
                $webhook->eventCategory,
                $webhook->url,
                ($webhook->raw['attributes']['signing_key'] ?? null) === $configured ? 'yes' : 'NO',
            ], $webhooks),
        );

        return self::SUCCESS;
    }
}
