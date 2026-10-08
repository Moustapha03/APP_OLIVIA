<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scans made without network on the bus, sent afterwards (step 7).
     *
     * - source: online (checked by the server at once) | offline (synced);
     * - client_id: UUID made by the phone for each offline scan; unique, so
     *   sending the same batch twice records nothing new;
     * - device_result / device_reason_code: what the phone showed on board
     *   (result / reason_code stay the server's verdict at scanned_at);
     * - synced_at: when the server received the offline scan.
     */
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('source', 10)->default('online')->after('period');
            $table->uuid('client_id')->nullable()->unique()->after('source');
            $table->string('device_result', 10)->nullable()->after('client_id');
            $table->string('device_reason_code', 30)->nullable()->after('device_result');
            $table->timestamp('synced_at')->nullable()->after('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
            $table->dropColumn(['source', 'client_id', 'device_result', 'device_reason_code', 'synced_at']);
        });
    }
};
