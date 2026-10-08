<?php

namespace App\Http\Requests\Espace;

use App\Models\Subscriber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * A customer adds or edits a subscriber of their account (themselves or a
 * relative). The photo is required to create one: every subscription is
 * nominative and checked against it.
 */
class SubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof Subscriber
            ? (bool) $this->user()?->can('update', $subscriber)
            : (bool) $this->user()?->can('passes.purchase');
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('phone');

        if (is_string($raw)) {
            $this->merge(['phone' => trim($raw) === '' ? null : (PhoneNumber::normalize($raw) ?? $raw)]);
        }

        if (is_string($this->input('full_name'))) {
            $this->merge(['full_name' => preg_replace('/\s+/', ' ', trim($this->input('full_name')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = ! $this->route('subscriber') instanceof Subscriber;

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'stop_id' => ['required', 'integer', Rule::exists('stops', 'id')->where('is_active', true)],
            'photo' => [
                $creating ? 'required' : 'nullable',
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
            'full_name.required' => 'Indiquez le nom et le prénom.',
            'phone.regex' => 'Numéro invalide : 10 chiffres commençant par 01, 05, 07, 21, 25 ou 27.',
            'stop_id.required' => 'Choisissez le point de montée.',
            'stop_id.exists' => 'Choisissez le point de montée.',
            'photo.required' => 'Ajoutez une photo du visage : elle est montrée au contrôleur.',
            'photo.image' => 'Le fichier doit être une photo (JPG, PNG ou WEBP).',
            'photo.max' => 'La photo est trop lourde (2 Mo maximum).',
            'photo.dimensions' => 'La photo doit faire entre 200 × 200 et 6 000 × 6 000 pixels.',
        ];
    }

    /**
     * @return array{full_name: string, phone: string|null, stop_id: int}
     */
    public function subscriberData(): array
    {
        /** @var array{full_name: string, phone: string|null, stop_id: int} */
        return $this->safe()->only(['full_name', 'phone', 'stop_id']) + ['phone' => null];
    }
}
