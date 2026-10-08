<?php

namespace App\Http\Requests\Admin;

use App\Models\Line;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name and active flag only. The order changes with the up/down buttons
 * (MoveZone), never by typing a number.
 */
class ZoneRequest extends FormRequest
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
        /** @var Line $line */
        $line = $this->route('line');
        $zone = $this->route('zone');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('zones', 'name')->where('line_id', $line->id)->ignore($zone instanceof Zone ? $zone->id : null),
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
            'name.required' => 'Indiquez le nom de la zone, par exemple « Bonoua / Bassam ».',
            'name.unique' => 'Cette zone existe déjà sur la ligne.',
        ];
    }
}
