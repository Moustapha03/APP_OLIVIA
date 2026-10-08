<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Customer self sign-up. Staff accounts are created by a manager (step 4).
 */
class RegisterRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email'))) ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => [...$this->phoneRules(), Rule::unique('users', 'phone')],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', 'max:255', Password::defaults()],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->phoneMessages(),
            'first_name.required' => 'Indiquez votre prénom.',
            'last_name.required' => 'Indiquez votre nom.',
            'email.email' => 'Adresse e-mail invalide. Exemple : nom@exemple.com',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.required' => 'Choisissez un mot de passe.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'password.min' => 'Le mot de passe doit avoir au moins 12 caractères.',
            'password.uncompromised' => 'Ce mot de passe est trop connu des pirates. Choisissez-en un autre.',
            'privacy.accepted' => 'Vous devez accepter l’utilisation de vos données pour créer un compte.',
        ];
    }
}
