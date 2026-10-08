<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            ? (bool) $this->user()?->can('update', $vehicle)
            : (bool) $this->user()?->can('create', Vehicle::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'registration' => is_string($this->input('registration'))
                ? strtoupper(preg_replace('/\s+/', ' ', trim($this->input('registration'))) ?? '')
                : $this->input('registration'),
            'line_id' => $this->input('line_id') ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'registration' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'registration')->ignore($vehicle instanceof Vehicle ? $vehicle->id : null)],
            'name' => ['required', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'line_id' => ['nullable', 'integer', Rule::exists('lines', 'id')],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration.required' => 'Indiquez l’immatriculation.',
            'registration.unique' => 'Ce véhicule est déjà enregistré.',
            'name.required' => 'Donnez un nom court, par exemple « Bus 12 ».',
            'capacity.required' => 'Indiquez le nombre de places.',
            'capacity.min' => 'Au moins 1 place.',
            'capacity.max' => '200 places maximum.',
        ];
    }
}
