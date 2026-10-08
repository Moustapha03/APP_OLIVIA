<?php

namespace App\Http\Requests\Espace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    /** Errors shown inside the confirmation modal. */
    protected $errorBag = 'accountDeletion';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Indiquez votre mot de passe pour confirmer.',
            'password.current_password' => 'Mot de passe incorrect.',
        ];
    }
}
