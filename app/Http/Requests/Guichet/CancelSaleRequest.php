<?php

namespace App\Http\Requests\Guichet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelSaleRequest extends FormRequest
{
    /** Errors are shown inside the cancellation modal of this sale. */
    public function errorBagName(): string
    {
        return 'cancel_'.$this->route('payment')?->reference;
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->errorBagName();
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('tickets.sell');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supervisor_login' => ['required', 'string', 'max:255'],
            'supervisor_password' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supervisor_login.required' => 'Le superviseur doit saisir son numéro de téléphone.',
            'supervisor_password.required' => 'Le superviseur doit saisir son mot de passe.',
            'reason.required' => 'Indiquez pourquoi la vente est annulée.',
            'reason.min' => 'Expliquez en quelques mots pourquoi la vente est annulée.',
        ];
    }
}
