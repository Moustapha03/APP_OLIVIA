<?php

namespace App\Http\Requests\Guichet;

use App\Models\CashSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('open', CashSession::class);
    }

    protected function prepareForValidation(): void
    {
        // "20 000" or "20.000 FCFA" → 20000
        if (is_string($this->input('opening_float'))) {
            $this->merge(['opening_float' => preg_replace('/\D+/', '', $this->input('opening_float'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'integer', 'min:0', 'max:'.(int) config('transport.counter.max_opening_float')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_float.required' => 'Comptez l’argent du tiroir et indiquez le montant (0 s’il est vide).',
            'opening_float.integer' => 'Un montant en FCFA, sans centimes.',
            'opening_float.max' => 'Ce montant semble trop élevé. Vérifiez le nombre de zéros.',
        ];
    }
}
