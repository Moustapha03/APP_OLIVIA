<?php

namespace App\Http\Requests\Guichet;

use App\Enums\FareType;
use App\Http\Requests\Concerns\CounterPaymentFields;
use App\Models\Subscriber;
use App\Support\SubscriptionMonths;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Monthly subscription at the desk: subscriber, formula, month, payment.
 * The zone (price) is the one of the subscriber's boarding point.
 */
class CounterSubscriptionRequest extends FormRequest
{
    use CounterPaymentFields;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('tickets.sell');
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCounterPayment();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subscriber_id' => ['required', 'integer', Rule::exists('subscribers', 'id')],
            'formula' => ['required', Rule::in(array_map(fn (FareType $t) => $t->value, FareType::subscriptions()))],
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'photo' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max((int) config('transport.subscriptions.photo_max_kb', 2048))
                    ->dimensions(Rule::dimensions()->minWidth(200)->minHeight(200)->maxWidth(6000)->maxHeight(6000)),
            ],
            ...$this->counterPaymentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->counterPaymentMessages(),
            'subscriber_id.required' => 'Choisissez d’abord l’abonné.',
            'formula.required' => 'Choisissez la formule.',
            'formula.in' => 'Choisissez la formule.',
            'month.required' => 'Choisissez le mois.',
            'month.regex' => 'Choisissez le mois.',
            'photo.image' => 'Le fichier doit être une photo (JPG, PNG ou WEBP).',
            'photo.max' => 'La photo est trop lourde (2 Mo maximum).',
            'photo.dimensions' => 'La photo doit faire entre 200 × 200 et 6 000 × 6 000 pixels.',
        ];
    }

    public function subscriber(): Subscriber
    {
        return Subscriber::query()->with('stop.zone')->findOrFail($this->validated('subscriber_id'));
    }

    public function formula(): FareType
    {
        return FareType::from($this->validated('formula'));
    }

    public function month(): ?Carbon
    {
        return SubscriptionMonths::parse($this->validated('month'));
    }
}
