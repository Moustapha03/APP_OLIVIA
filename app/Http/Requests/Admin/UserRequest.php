<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\Concerns\NormalizesPhone;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (no {user} in the route) or update an account from the back office.
 * Whether the actor may give the chosen role is checked with UserPolicy.
 */
class UserRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        $target = $this->target();

        return $target instanceof User
            ? (bool) $this->user()?->can('update', $target)
            : (bool) $this->user()?->can('create', User::class);
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
        $target = $this->target();
        $ignore = $target instanceof User ? $target->id : null;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => [...$this->phoneRules(), Rule::unique('users', 'phone')->ignore($ignore)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            'role' => ['required', Rule::enum(RoleName::class)],
            'status' => [$target instanceof User ? 'required' : 'nullable', Rule::enum(UserStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->target();
            $role = RoleName::tryFrom((string) $this->input('role'));

            // Keeping the person's current role is not "giving" it (a manager
            // may still edit the name of an agent whose role they cannot grant).
            $unchanged = $target !== null && $role !== null && $target->getRoleNames()->first() === $role->value;

            if ($role !== null && ! $unchanged && ! $this->user()?->can('assignRole', [$target ?? new User, $role])) {
                $validator->errors()->add('role', 'Vous ne pouvez pas attribuer ce rôle.');
            }

            // Phone and e-mail are the login of a staff account: super-admin only.
            if ($target !== null && ! $this->user()?->can('changeIdentity', $target)) {
                if ($this->input('phone') !== $target->phone) {
                    $validator->errors()->add('phone', 'Seul un super-administrateur peut changer le numéro d’un membre du personnel.');
                }

                if (($this->input('email') ?: null) !== $target->email) {
                    $validator->errors()->add('email', 'Seul un super-administrateur peut changer l’e-mail d’un membre du personnel.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->phoneMessages(),
            'phone.unique' => 'Ce numéro est déjà utilisé par un autre compte.',
            'first_name.required' => 'Indiquez le prénom.',
            'last_name.required' => 'Indiquez le nom.',
            'email.email' => 'Adresse e-mail invalide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'role.required' => 'Choisissez un rôle.',
        ];
    }

    public function role(): RoleName
    {
        return RoleName::from($this->validated('role'));
    }

    public function status(): UserStatus
    {
        return UserStatus::tryFrom((string) $this->validated('status')) ?? UserStatus::Active;
    }

    /**
     * @return array{first_name: string, last_name: string, phone: string, email: string|null}
     */
    public function identity(): array
    {
        /** @var array{first_name: string, last_name: string, phone: string, email: string|null} */
        return $this->safe()->only(['first_name', 'last_name', 'phone', 'email']) + ['email' => null];
    }

    private function target(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
