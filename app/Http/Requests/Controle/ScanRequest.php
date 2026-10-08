<?php

namespace App\Http\Requests\Controle;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Scanned QR content, or a receipt number typed by the controller.
 */
class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('scans.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Scannez un QR code ou saisissez le numéro du reçu.',
            'code.max' => 'Ce code est trop long : ce n’est pas un titre de transport.',
        ];
    }
}
