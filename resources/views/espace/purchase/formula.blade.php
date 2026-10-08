@php
    use App\Enums\FareType;
    use App\Support\SubscriptionMonths;
    $subscriptionTypes = array_filter(FareType::subscriptions(), fn ($t) => isset($prices[$t->value]));
    $readySubscribers = $subscribers->filter->hasPhoto();
@endphp

<x-layouts.espace title="Acheter · Formule">
    <div class="space-y-6">
        <x-page-header :title="'Montée : '.$stop->name" :subtitle="'Zone '.$stop->zone->name" :back="route('espace.purchase.create')" back-label="Changer" />

        <x-stepper :steps="['Montée', 'Formule', 'Paiement']" :current="2" />

        {{-- Daily ticket --}}
        @isset($prices['daily'])
            <form method="POST" action="{{ route('espace.purchase.store') }}">
                @csrf
                <input type="hidden" name="stop_id" value="{{ $stop->id }}">
                <input type="hidden" name="type" value="daily">
                <x-card title="Ticket du jour" :subtitle="$dailyRides.' trajets aujourd’hui (matin et soir), valable jusqu’à 23 h 59.'">
                    <x-slot:actions><x-money :amount="$prices['daily']" class="text-xl font-semibold" /></x-slot:actions>
                    <x-button type="submit" size="lg" block icon="smartphone" loading-text="Ouverture du paiement…">Payer le ticket du jour</x-button>
                </x-card>
            </form>
        @endisset

        {{-- Monthly subscription --}}
        @if ($subscriptionTypes !== [])
            <x-card title="Abonnement du mois" subtitle="Personnel, avec photo. Valable du 1er au dernier jour du mois.">
                @if ($readySubscribers->isEmpty())
                    <x-alert variant="warning" title="Ajoutez d’abord l’abonné">
                        L’abonnement est personnel : enregistrez la personne qui voyage, avec sa photo (vous-même ou un proche).
                    </x-alert>
                    <x-button :href="route('espace.subscribers.index', ['retour' => route('espace.purchase.formula', $stop, false)])" variant="secondary" block icon="user-plus" class="mt-4">Ajouter un abonné</x-button>
                @else
                    <form method="POST" action="{{ route('espace.purchase.store') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="stop_id" value="{{ $stop->id }}">

                        <fieldset class="space-y-3">
                            <legend class="mb-2 text-base font-medium text-slate-800">Formule</legend>
                            @foreach ($subscriptionTypes as $type)
                                <x-choice name="type" :value="$type->value" :title="$type->shortLabel()" :amount="$prices[$type->value]" icon="id-card" :checked="($selected ?? FareType::Monthly) === $type" required />
                            @endforeach
                            @error('type')<x-alert variant="danger">{{ $message }}</x-alert>@enderror
                        </fieldset>

                        <fieldset class="space-y-3">
                            <legend class="mb-2 text-base font-medium text-slate-800">Pour qui ?</legend>
                            @foreach ($readySubscribers as $subscriber)
                                <x-choice name="subscriber_id" :value="$subscriber->id" :title="$subscriber->full_name" :description="$subscriber->formatted_phone" icon="user-round" :checked="$loop->first" required />
                            @endforeach
                            @error('subscriber_id')<x-alert variant="danger">{{ $message }}</x-alert>@enderror
                            <a href="{{ route('espace.subscribers.index', ['retour' => route('espace.purchase.formula', $stop, false)]) }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-primary-700 underline underline-offset-4">Ajouter une autre personne</a>
                        </fieldset>

                        <fieldset class="space-y-3">
                            <legend class="mb-2 text-base font-medium text-slate-800">Mois</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($months as $month)
                                    <x-choice name="month" :value="$month->format('Y-m')" :title="ucfirst(SubscriptionMonths::label($month))" :description="$loop->first ? 'Mois en cours' : 'Mois prochain'" icon="calendar" :checked="$loop->first" required />
                                @endforeach
                            </div>
                            @error('month')<x-alert variant="danger">{{ $message }}</x-alert>@enderror
                        </fieldset>

                        <x-button type="submit" size="lg" block icon="smartphone" loading-text="Ouverture du paiement…">Payer l’abonnement</x-button>
                    </form>
                @endif
            </x-card>
        @endif

        <p class="text-center text-sm text-slate-600">Paiement Mobile Money (Orange, MTN, Moov, Wave). Le prix est celui d’aujourd’hui. Vous pouvez aussi payer à l’agence.</p>
    </div>
</x-layouts.espace>
