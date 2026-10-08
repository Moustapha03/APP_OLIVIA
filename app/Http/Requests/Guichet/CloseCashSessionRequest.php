<?php

namespace App\Http\Requests\Guichet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cash.close');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('counted_amount'))) {
            $this->merge(['counted_amount' => preg_replace('/\D+/', '', $this->input('counted_amount'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'justification' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counted_amount.required' => 'Comptez l’argent du tiroir et indiquez le montant.',
            'counted_amount.integer' => 'Un montant en FCFA, sans centimes.',
        ];
    }
}
