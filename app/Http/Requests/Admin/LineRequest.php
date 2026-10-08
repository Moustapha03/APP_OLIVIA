<?php

namespace App\Http\Requests\Admin;

use App\Models\Line;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $line = $this->route('line');

        return $line instanceof Line
            ? (bool) $this->user()?->can('update', $line)
            : (bool) $this->user()?->can('create', Line::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : $this->input('code'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $line = $this->route('line');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('lines', 'code')->ignore($line instanceof Line ? $line->id : null)],
            'name' => ['required', 'string', 'max:120'],
            'destination_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Indiquez un code court, par exemple « ANGRE ».',
            'code.alpha_dash' => 'Le code ne contient que des lettres, des chiffres et des tirets.',
            'code.unique' => 'Ce code est déjà utilisé par une autre ligne.',
            'name.required' => 'Indiquez le nom de la ligne.',
            'destination_name.required' => 'Indiquez la destination, par exemple « Angré ».',
        ];
    }
}
