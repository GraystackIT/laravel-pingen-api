<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This table is the replay guard, not a log.
     *
     * Pingen signs a delivery over its body alone — no timestamp, no delivery
     * nonce inside the signed content — so a captured request stays valid
     * forever and can be replayed by anyone who recorded it. Remembering which
     * delivery ids have been handled, and ignoring a repeat, is what closes
     * that. It also makes Pingen's own retries harmless, which is why it is
     * created even when letter storage is switched off.
     */
    public function up(): void
    {
        Schema::create('pingen_webhook_events', function (Blueprint $table): void {
            $table->id();

            /** The delivery id, from the payload. Unique: this is the guard. */
            $table->string('event_id')->unique();

            $table->string('letter_id')->nullable()->index();

            $table->string('category')->nullable();

            $table->json('payload')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pingen_webhook_events');
    }
};
