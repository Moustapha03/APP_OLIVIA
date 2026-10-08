<?php

namespace App\Actions\Sales;

use App\Actions\Passes\EnsureSubscriptionCanBeSold;
use App\Actions\Passes\IssuePass;
use App\Enums\FareType;
use App\Enums\PassChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Zone;
use App\Services\Receipts\ReceiptNumbers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sale at the agency desk: cash, or a Mobile Money transfer already
 * received on the company number (Wave…), recorded with its reference.
 * The money is in hand: the payment is confirmed and the pass issued in
 * the same transaction, with a receipt number (BA-2026-0018).
 *
 * - price = fare of the boarding point's zone in force today (rule 1);
 * - a different amount (discount) needs "sales.discount" and a reason;
 * - a transfer reference can be recorded only once (no double counting);
 * - the register row is locked and must be the agent's, still open.
 */
class SellAtCounter
{
    public const MIN_DISCOUNT_REASON = 5;

    public function __construct(
        private readonly IssuePass $issuePass,
        private readonly ReceiptNumbers $receipts,
        private readonly EnsureSubscriptionCanBeSold $subscriptionRules,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(
        User $agent,
        CashSession $session,
        Zone $zone,
        ?Stop $stop,
        FareType $type,
        PaymentMethod $method,
        ?Subscriber $subscriber = null,
        ?Carbon $month = null,
        ?string $externalReference = null,
        ?int $agreedAmount = null,
        ?string $discountReason = null,
        ?User $approver = null,
    ): Payment {
        $zone->loadMissing('line');

        if (! $zone->is_active || ! $zone->line->is_active || ($stop !== null && (! $stop->is_active || $stop->zone_id !== $zone->id))) {
            throw ValidationException::withMessages(['zone_id' => 'Cette zone ou ce point de montée n’est plus desservi.']);
        }

        if ($type->isSubscription()) {
            $this->subscriptionRules->handle($subscriber, $month);
        }

        $fare = $zone->fareOn($type);

        if ($fare === null) {
            throw ValidationException::withMessages(['type' => 'Pas de tarif en vigueur pour cette formule dans cette zone.']);
        }

        $externalReference = $this->checkMethod($method, $externalReference);
        $amount = $this->checkAmount($agent, $approver, $fare->amount, $agreedAmount, $discountReason);
        // Who allowed the price: the agent if they hold sales.discount, else the manager who validated it.
        $discountedBy = $agent->can('sales.discount') ? $agent : $approver;

        return DB::transaction(function () use ($agent, $session, $zone, $stop, $type, $method, $subscriber, $month, $externalReference, $fare, $amount, $discountReason, $discountedBy) {
            /** @var CashSession $locked */
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen() || $locked->user_id !== $agent->id) {
                throw ValidationException::withMessages(['type' => 'Votre caisse n’est pas ouverte.']);
            }

            $discounted = $amount !== $fare->amount;

            $payment = new Payment;
            $payment->forceFill([
                'reference' => 'TT'.Str::upper((string) Str::ulid()),
                'receipt_number' => $this->receipts->next($type),
                'user_id' => $type->isSubscription() ? $subscriber?->user_id : null,
                'subscriber_id' => $type->isSubscription() ? $subscriber?->id : null,
                'purpose' => $type,
                'zone_id' => $zone->id,
                'stop_id' => $stop?->id,
                'fare_id' => $fare->id,
                'period_month' => $type->isSubscription() ? $month?->copy()->startOfMonth() : null,
                'list_amount' => $fare->amount,
                'amount' => $amount,
                'discount_reason' => $discounted ? trim((string) $discountReason) : null,
                'discounted_by' => $discounted ? $discountedBy?->id : null,
                'currency' => (string) config('transport.currency', 'XOF'),
                'gateway' => 'counter',
                'payment_method' => $method,
                'external_reference' => $externalReference,
                'cash_session_id' => $locked->id,
                'sold_by' => $agent->id,
                'status' => PaymentStatus::Succeeded,
                'confirmed_at' => now(),
            ])->save();

            $pass = $this->issuePass->handle($payment, PassChannel::Counter, $agent->id);

            activity('cash')->causedBy($agent)->performedOn($payment)
                ->event($discounted ? 'sale.discounted' : 'sale.counter')
                ->withProperties([
                    'receipt' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'list_amount' => $payment->list_amount,
                    'discount_reason' => $payment->discount_reason,
                    'discount_approved_by' => $discounted ? $discountedBy?->name : null,
                    'method' => $method->value,
                    'external_reference' => $externalReference,
                    'pass' => $pass->passUuid(),
                    'cash_session' => $locked->id,
                ])
                ->log($discounted ? 'Vente à l’agence avec remise' : 'Vente à l’agence');

            return $payment;
        });
    }

    /**
     * @throws ValidationException
     */
    private function checkMethod(PaymentMethod $method, ?string $reference): ?string
    {
        if (! in_array($method, PaymentMethod::atCounter(), true)) {
            throw ValidationException::withMessages(['payment_method' => 'Moyen de paiement non accepté à l’agence.']);
        }

        if (! $method->isTransfer()) {
            return null;
        }

        $reference = strtoupper(trim((string) $reference));

        if ($reference === '') {
            throw ValidationException::withMessages(['external_reference' => 'Saisissez la référence du transfert '.$method->label().'.']);
        }

        $alreadyUsed = Payment::query()
            ->where('payment_method', $method)
            ->where('external_reference', $reference)
            ->first();

        if ($alreadyUsed !== null) {
            throw ValidationException::withMessages([
                'external_reference' => 'Ce transfert '.$method->label().' est déjà enregistré (reçu '.($alreadyUsed->receipt_number ?? $alreadyUsed->reference).').',
            ]);
        }

        return $reference;
    }

    /**
     * @throws ValidationException
     */
    private function checkAmount(User $agent, ?User $approver, int $listAmount, ?int $agreed, ?string $reason): int
    {
        if ($agreed === null || $agreed === $listAmount) {
            return $listAmount;
        }

        // The agent who takes the money never lowers the price alone (ApproveDiscount).
        if (! $agent->can('sales.discount') && ($approver === null || ! $approver->can('sales.discount'))) {
            throw ValidationException::withMessages(['amount' => 'Prix différent de la grille : un responsable doit le valider avec son téléphone et son mot de passe.']);
        }

        if ($agreed <= 0 || $agreed > $listAmount) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être entre 1 et le prix de la grille.']);
        }

        if (mb_strlen(trim((string) $reason)) < self::MIN_DISCOUNT_REASON) {
            throw ValidationException::withMessages(['discount_reason' => 'Indiquez pourquoi le prix est différent de la grille.']);
        }

        return $agreed;
    }
}
