<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ExpenseRequest extends FormRequest
{
    /** Upper bound against typing mistakes (an extra zero…). */
    public const MAX_AMOUNT = 50_000_000;

    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? (bool) $this->user()?->can('update', $expense)
            : (bool) $this->user()?->can('create', Expense::class);
    }

    protected function prepareForValidation(): void
    {
        // "25 000", "25.000" or "25000 FCFA" → 25000
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => preg_replace('/\D+/', '', $this->input('amount'))]);
        }

        foreach (['label', 'reference', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim((string) preg_replace('/[ \t]+/', ' ', $this->input($field))) ?: null]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2000-01-01'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            'label' => ['required', 'string', 'min:2', 'max:200'],
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::forExpenses()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max(5 * 1024)],
            'remove_attachment' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Choisissez le type de dépense.',
            'expense_category_id.exists' => 'Ce type de dépense n’existe pas ou est archivé.',
            'spent_on.required' => 'Indiquez la date de la dépense.',
            'spent_on.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'amount.required' => 'Indiquez le montant.',
            'amount.integer' => 'Le montant est un nombre entier de FCFA, sans centimes.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'amount.max' => 'Ce montant semble trop élevé. Vérifiez le nombre de zéros.',
            'label.required' => 'Décrivez la dépense (ex. : gasoil Bus 12).',
            'payment_method.required' => 'Choisissez le moyen de paiement.',
            'attachment.mimes' => 'Le justificatif doit être une photo (JPG, PNG, WEBP) ou un PDF.',
            'attachment.max' => 'Le justificatif est trop lourd (5 Mo maximum).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function expenseData(): array
    {
        return collect($this->validated())->except(['attachment', 'remove_attachment'])->all();
    }
}
