<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pingen_letters', function (Blueprint $table): void {
            $table->id();

            /** The UUID Pingen assigned. Unique, so a repeated webhook or a retried send updates rather than duplicates. */
            $table->string('letter_id')->unique();

            /** The organisation the letter was sent from — an application may hold several. */
            $table->string('organisation_id')->index();

            $table->string('file_original_name');

            $table->unsignedInteger('file_pages')->nullable();

            /** Status as Pingen reported it, not as this package mapped it: the value set is open. */
            $table->string('status')->default('draft')->index();

            $table->string('country', 2)->nullable();

            $table->string('price_currency', 3)->nullable();

            /** Price in major units, as the API returns it. */
            $table->decimal('price_value', 10, 2)->nullable();

            $table->string('tracking_number')->nullable();

            $table->timestamp('submitted_at')->nullable();

            /** The whole resource object or webhook body, for anything not modelled above. */
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pingen_letters');
    }
};
