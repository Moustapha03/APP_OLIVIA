<?php

namespace App\Http\Requests\Guichet;

use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('customers.create');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
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
            'phone.unique' => 'Ce numéro a déjà un compte : recherchez-le.',
            'first_name.required' => 'Indiquez le prénom.',
            'last_name.required' => 'Indiquez le nom.',
            'privacy.accepted' => 'Le client doit accepter l’utilisation de ses données (loi 2013-450).',
        ];
    }
}
