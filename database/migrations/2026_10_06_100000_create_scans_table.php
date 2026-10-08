<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every pass check made on board, accepted or not (step 7).
     *
     * - kind: ticket | subscription | unknown (forged or unreadable code);
     * - result: valid | refused | warning, reason_code: machine-readable
     *   cause (ok, photo_missing, replay, expired…), reason: French text
     *   shown to the controller;
     * - anti-replay looks up the last accepted scan of the same pass.
     */
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 10);
            $table->string('reason_code', 30);
            $table->string('reason');
            $table->string('period', 10)->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['ticket_id', 'scanned_at']);
            $table->index(['subscription_id', 'scanned_at']);
            $table->index(['user_id', 'scanned_at']);
            $table->index(['result', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
