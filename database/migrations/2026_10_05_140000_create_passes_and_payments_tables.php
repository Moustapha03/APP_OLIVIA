<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscribers, payments and the passes they pay for.
     *
     * - A subscriber (abonné) is a person who rides: name, contact phone
     *   (may be shared: a parent pays for a relative), photo, usual boarding
     *   point. They may be attached to a customer account (user) or not.
     * - A pass exists only once its payment is confirmed (business rule 5).
     * Amounts are integers in FCFA.
     */
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            // Customer account that manages / pays for this subscriber, if any.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 150);
            $table->string('phone', 20)->nullable()->index(); // E.164, NOT unique
            $table->foreignId('stop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('full_name');
        });

        // Gapless yearly receipt numbers (BA-2026-0018, TJ-2026-00001).
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 5);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'year']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Technical reference, sent to the aggregator as transaction id.
            // Doubles as idempotency key: one payment = at most one pass.
            $table->string('reference', 40)->unique();
            // Human receipt number, given once paid.
            $table->string('receipt_number', 20)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20); // FareType
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('stop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fare_id')->nullable()->constrained()->nullOnDelete();
            // Subscription month (first day), null for a daily ticket.
            $table->date('period_month')->nullable();
            // Grid price of the day, and the amount actually paid (discount).
            $table->unsignedInteger('list_amount');
            $table->unsignedInteger('amount');
            $table->string('discount_reason', 500)->nullable();
            $table->foreignId('discounted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('XOF');
            $table->string('gateway', 20); // fake | cinetpay | counter
            // cash | wave | orange_money | mtn_momo | moov_money | online
            $table->string('payment_method', 20)->nullable();
            // Mobile Money transfer id typed at the desk (unique per method).
            $table->string('external_reference', 100)->nullable();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('gateway_transaction_id', 100)->nullable()->index();
            $table->string('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->nullableMorphs('payable');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['payment_method', 'external_reference']);
            $table->index(['cash_session_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'confirmed_at']);
            $table->index('period_month');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('stop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fare_id')->nullable()->constrained()->nullOnDelete();
            // Unique: a payment can never issue two passes (replayed webhook…).
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('price_paid');
            $table->date('valid_on')->index();
            $table->unsignedTinyInteger('rides_allowed');
            $table->unsignedTinyInteger('rides_used')->default(0);
            $table->string('channel', 20); // online | counter
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'valid_on']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscriber_id')->constrained()->restrictOnDelete();
            // Account that bought it (kept for accounting, detached on account deletion).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('stop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fare_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('formula', 20); // monthly | monthly_morning | monthly_evening
            $table->unsignedInteger('price_paid');
            // A calendar month: first day → last day.
            $table->date('starts_on');
            $table->date('ends_on')->index();
            $table->string('channel', 20);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subscriber_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receipt_sequences');
        Schema::dropIfExists('subscribers');
    }
};
