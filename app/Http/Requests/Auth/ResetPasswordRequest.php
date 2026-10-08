<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', 'max:255', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indiquez votre adresse e-mail.',
            'email.email' => 'Adresse e-mail invalide.',
            'password.required' => 'Choisissez un nouveau mot de passe.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'password.min' => 'Le mot de passe doit avoir au moins 12 caractères.',
            'password.uncompromised' => 'Ce mot de passe est trop connu des pirates. Choisissez-en un autre.',
        ];
    }
}
