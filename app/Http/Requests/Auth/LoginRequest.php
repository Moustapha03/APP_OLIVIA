<?php

namespace App\Http\Requests\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * "login" accepts a phone number in any common format, or an e-mail.
 * Brute force: route throttle "login" + account lockout (AuthenticateUser).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Indiquez votre numéro de téléphone.',
            'password.required' => 'Indiquez votre mot de passe.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        return app(AuthenticateUser::class)->handle(
            login: $this->string('login')->trim()->toString(),
            password: $this->string('password')->toString(),
            remember: $this->boolean('remember'),
            ip: (string) $this->ip(),
        );
    }
}
