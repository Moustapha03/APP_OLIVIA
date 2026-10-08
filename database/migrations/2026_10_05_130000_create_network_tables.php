<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Network reference data.
     *
     *   line  "Bonoua – Angré"  (everyone rides to the destination, Angré)
     *    └ zones (fare zones), ordered by distance from the destination:
     *        1 Koumassi / Marcory · 2 Anani / Gonzague / Port-Bouët · 3 Bonoua / Bassam
     *        └ stops = boarding points (Zouglou, Moossou, Carrefour Aéroport…)
     *   fares: price of a zone for a formula, from a date.
     *
     * Amounts are integers in FCFA.
     */
    public function up(): void
    {
        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            // Where everybody goes in the morning (and leaves from in the evening).
            $table->string('destination_name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            // 1 = closest to the destination. A subscription of zone N is
            // valid when boarding in zones 1..N (business rule 3).
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['line_id', 'position']);
            $table->unique(['line_id', 'name']);
        });

        Schema::create('stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['zone_id', 'name']);
        });

        Schema::create('fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            // daily | monthly (matin & soir) | monthly_morning | monthly_evening
            $table->string('type', 20);
            $table->unsignedInteger('amount');
            // Never edited: a new fare is scheduled from a date and closes
            // the previous one the day before.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['zone_id', 'type', 'effective_from']);
            $table->index(['zone_id', 'type', 'effective_to']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration', 20)->unique();
            $table->string('name', 60);
            $table->unsignedSmallInteger('capacity');
            $table->foreignId('line_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('fares');
        Schema::dropIfExists('stops');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('lines');
    }
};
