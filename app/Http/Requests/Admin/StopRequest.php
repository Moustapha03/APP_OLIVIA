<?php

namespace App\Http\Requests\Admin;

use App\Models\Stop;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Boarding point of a zone.
 */
class StopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('stops.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Zone $zone */
        $zone = $this->route('zone');
        $stop = $this->route('stop');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('stops', 'name')->where('zone_id', $zone->id)->ignore($stop instanceof Stop ? $stop->id : null),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Indiquez le nom du point de montée.',
            'name.unique' => 'Ce point de montée existe déjà dans la zone.',
        ];
    }
}
