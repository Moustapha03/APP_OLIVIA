<?php

namespace App\Http\Requests\Guichet;

use App\Http\Requests\Concerns\CounterPaymentFields;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Daily ticket at the desk: one click on the zone card.
 */
class CounterSaleRequest extends FormRequest
{
    use CounterPaymentFields;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('tickets.sell');
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCounterPayment();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')],
            ...$this->counterPaymentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->counterPaymentMessages(),
            'zone_id.required' => 'Choisissez la zone du client.',
            'zone_id.exists' => 'Choisissez la zone du client.',
        ];
    }

    public function zone(): Zone
    {
        return Zone::query()->findOrFail($this->validated('zone_id'));
    }
}
