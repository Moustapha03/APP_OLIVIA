<?php

namespace App\Http\Requests\Concerns;

use App\Actions\Sales\ApproveDiscount;
use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payment fields of a desk sale: method (cash or a Mobile Money transfer
 * with its reference), optional negotiated amount and its reason.
 * The grid price itself is never read from the form.
 */
trait CounterPaymentFields
{
    protected function prepareCounterPayment(): void
    {
        if (is_string($this->input('amount'))) {
            $digits = preg_replace('/\D+/', '', $this->input('amount'));
            $this->merge(['amount' => $digits === '' ? null : $digits]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function counterPaymentRules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::atCounter()))],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'discount_reason' => ['nullable', 'string', 'max:500'],
            // Manager validating a price below the grid (App\Actions\Sales\ApproveDiscount).
            'approver_login' => ['nullable', 'string', 'max:150'],
            'approver_password' => ['nullable', 'string', 'max:200'],
            'print' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function counterPaymentMessages(): array
    {
        return [
            'payment_method.required' => 'Choisissez le moyen de paiement.',
            'payment_method.in' => 'Moyen de paiement non accepté à l’agence.',
            'amount.integer' => 'Un montant en FCFA, sans centimes.',
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('payment_method'));
    }

    /**
     * Manager who validated a price below the grid, when the agent may not grant it.
     *
     * @throws ValidationException
     */
    public function discountApprover(ApproveDiscount $approve): ?User
    {
        $agent = $this->user();
        $amount = $this->agreedAmount();

        if ($agent === null || $amount === null || $agent->can('sales.discount') || ! $this->filled('approver_login')) {
            return null;
        }

        return $approve->handle($agent, (string) $this->validated('approver_login'), (string) $this->validated('approver_password'));
    }

    public function agreedAmount(): ?int
    {
        $amount = $this->validated('amount');

        return $amount === null ? null : (int) $amount;
    }
}
