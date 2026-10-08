<?php

namespace App\Http\Requests\Guichet;

use App\Models\Subscriber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Subscriber created or edited at the agency. The contact phone may be
 * shared by several subscribers (a parent pays for a relative).
 */
class SubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof Subscriber
            ? (bool) $this->user()?->can('update', $subscriber)
            : (bool) $this->user()?->can('customers.create');
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('phone');

        if (is_string($raw)) {
            $this->merge(['phone' => trim($raw) === '' ? null : (PhoneNumber::normalize($raw) ?? $raw)]);
        }

        $this->merge([
            'full_name' => is_string($this->input('full_name')) ? preg_replace('/\s+/', ' ', trim($this->input('full_name'))) : $this->input('full_name'),
            'link_account' => $this->boolean('link_account'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'stop_id' => ['required', 'integer', Rule::exists('stops', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'link_account' => ['boolean'],
            'photo' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max((int) config('transport.subscriptions.photo_max_kb', 2048))
                    ->dimensions(Rule::dimensions()->minWidth(200)->minHeight(200)->maxWidth(6000)->maxHeight(6000)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Indiquez le nom de l’abonné.',
            'phone.regex' => 'Numéro invalide : 10 chiffres commençant par 01, 05, 07, 21, 25 ou 27.',
            'stop_id.required' => 'Choisissez le point de montée.',
            'stop_id.exists' => 'Choisissez le point de montée.',
            'photo.image' => 'Le fichier doit être une photo (JPG, PNG ou WEBP).',
            'photo.max' => 'La photo est trop lourde (2 Mo maximum).',
            'photo.dimensions' => 'La photo doit faire entre 200 × 200 et 6 000 × 6 000 pixels.',
        ];
    }

    /**
     * @return array{full_name: string, phone: string|null, stop_id: int, notes: string|null}
     */
    public function subscriberData(): array
    {
        /** @var array{full_name: string, phone: string|null, stop_id: int, notes: string|null} */
        return $this->safe()->only(['full_name', 'phone', 'stop_id', 'notes']) + ['phone' => null, 'notes' => null];
    }
}
