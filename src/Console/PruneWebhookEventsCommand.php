<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Console;

use GraystackIT\Pingen\Models\PingenWebhookEvent;
use Illuminate\Console\Command;

/**
 * Removes old webhook delivery records.
 *
 * The replay guard only has to remember a delivery for as long as it could
 * plausibly be replayed or retried; keeping every row forever turns a guard
 * into a growing table. The default of 30 days is comfortably longer than any
 * retry window.
 */
class PruneWebhookEventsCommand extends Command
{
    protected $signature = 'pingen:prune-webhook-events {--days=30 : How many days of deliveries to keep}';

    protected $description = 'Remove Pingen webhook delivery records older than the given number of days';

    /**
     * Run the command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = PingenWebhookEvent::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Removed {$deleted} Pingen webhook delivery record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
