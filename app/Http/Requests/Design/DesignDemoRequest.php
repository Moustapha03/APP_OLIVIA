<?php

namespace App\Http\Requests\Design;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DesignDemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('design.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:form,confirm'],
            'phone' => ['required_if:type,form', 'nullable', 'regex:/^(01|05|07|25|27)\d{8}$/'],
            'stop_id' => ['required_if:type,form', 'nullable', 'integer', 'between:1,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required_if' => 'Indiquez votre numéro de téléphone.',
            'phone.regex' => 'Le numéro doit avoir 10 chiffres et commencer par 01, 05, 07, 25 ou 27.',
            'stop_id.required_if' => 'Choisissez votre arrêt.',
        ];
    }
}
