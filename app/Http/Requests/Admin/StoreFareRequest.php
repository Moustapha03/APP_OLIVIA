<?php

namespace App\Http\Requests\Admin;

use App\Enums\FareType;
use App\Models\Fare;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFareRequest extends FormRequest
{
    /** Upper bound against typing mistakes (an extra zero…). */
    public const MAX_AMOUNT = 500000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Fare::class);
    }

    protected function prepareForValidation(): void
    {
        // "1 500", "1.500" or "1500 FCFA" → 1500
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => preg_replace('/\D+/', '', $this->input('amount'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')],
            'type' => ['required', Rule::enum(FareType::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zone_id.required' => 'Choisissez la zone.',
            'zone_id.exists' => 'Cette zone n’existe pas.',
            'type.required' => 'Choisissez la formule.',
            'amount.required' => 'Indiquez le prix.',
            'amount.integer' => 'Le prix est un nombre entier de FCFA, sans centimes.',
            'amount.min' => 'Le prix doit être supérieur à 0.',
            'amount.max' => 'Ce prix semble trop élevé. Vérifiez le nombre de zéros.',
            'effective_from.required' => 'Indiquez à partir de quand le prix s’applique.',
            'effective_from.after_or_equal' => 'La date d’effet ne peut pas être dans le passé.',
        ];
    }
}
