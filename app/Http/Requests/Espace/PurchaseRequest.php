<?php

namespace App\Http\Requests\Espace;

use App\Enums\FareType;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Support\SubscriptionMonths;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * What the customer wants: boarding point, formula, and for a
 * subscription the subscriber and the month. Never a price.
 */
class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('passes.purchase');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subscription = FareType::tryFrom((string) $this->input('type'))?->isSubscription() ?? false;

        return [
            'stop_id' => ['required', 'integer', Rule::exists('stops', 'id')],
            'type' => ['required', Rule::enum(FareType::class)],
            'subscriber_id' => [$subscription ? 'required' : 'nullable', 'integer', Rule::exists('subscribers', 'id')],
            'month' => [$subscription ? 'required' : 'nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stop_id.required' => 'Choisissez votre point de montée.',
            'stop_id.exists' => 'Ce point de montée n’existe pas.',
            'type.required' => 'Choisissez une formule.',
            'subscriber_id.required' => 'Choisissez pour qui est l’abonnement.',
            'month.required' => 'Choisissez le mois.',
        ];
    }

    public function stop(): Stop
    {
        return Stop::query()->with('zone.line')->findOrFail($this->validated('stop_id'));
    }

    public function fareType(): FareType
    {
        return FareType::from($this->validated('type'));
    }

    public function subscriber(): ?Subscriber
    {
        $id = $this->validated('subscriber_id');

        return $id === null ? null : Subscriber::query()->find((int) $id);
    }

    public function month(): ?Carbon
    {
        return SubscriptionMonths::parse($this->validated('month'));
    }
}
