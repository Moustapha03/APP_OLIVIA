<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Change one's own password (also used for the forced change after a
 * temporary password given by staff).
 */
class UpdatePasswordRequest extends FormRequest
{
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
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'max:255', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Indiquez votre mot de passe actuel.',
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.required' => 'Choisissez un nouveau mot de passe.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l’actuel.',
            'password.min' => 'Le mot de passe doit avoir au moins 12 caractères.',
            'password.uncompromised' => 'Ce mot de passe est trop connu des pirates. Choisissez-en un autre.',
        ];
    }
}
