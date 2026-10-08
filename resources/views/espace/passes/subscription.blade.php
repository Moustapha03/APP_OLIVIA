@php
    use App\Enums\PassStatus;
    $reminderDays = (int) config('transport.subscriptions.reminder_days_before', 3);
    [$status, $statusLabel] = match (true) {
        $subscription->status !== PassStatus::Active => ['neutral', $subscription->status->label()],
        $subscription->isExpired() => ['neutral', 'Terminé'],
        ! $subscription->hasStarted() => ['primary', 'À partir du 1er'],
        $subscription->expiresSoon($reminderDays) => ['warning', 'Fin dans '.$subscription->daysLeft().' j'],
        default => ['success', 'Valide'],
    };
    $holder = $subscription->subscriber;
    $usable = $subscription->status === PassStatus::Active && ! $subscription->isExpired();
@endphp

<x-layouts.espace title="Abonnement">
    <div class="space-y-6">
        <x-page-header title="Abonnement" :back="route('espace.passes.index')" back-label="Mes titres" />

        @if ($subscription->expiresSoon($reminderDays))
            <x-alert variant="warning" title="Cet abonnement se termine le {{ $subscription->ends_on->translatedFormat('j F') }}">
                Achetez le mois prochain maintenant pour continuer à voyager sans interruption.
            </x-alert>
        @endif

        <x-ticket
            type="monthly"
            :title="ucfirst($subscription->monthLabel())"
            :subtitle="$subscription->formula->shortLabel().' · vers '.$subscription->zone->line->destination_name"
            :status="$status"
            :status-label="$statusLabel"
        >
            <div class="flex items-center gap-3">
                @if ($holder->hasPhoto())
                    <img src="{{ route('photos.subscriber', $holder) }}" alt="Photo de {{ $holder->full_name }}" class="size-14 rounded-2xl object-cover ring-2 ring-white/40">
                @endif
                <div>
                    <p class="text-lg font-semibold">{{ $holder->full_name }}</p>
                    <p class="text-sm text-primary-100">Montée : {{ $subscription->stop?->name ?? $subscription->zone->name }} · zone {{ $subscription->zone->name }} et plus proches</p>
                </div>
            </div>

            <x-slot:stub>
                <div class="flex flex-col items-center gap-3">
                    @if ($usable)
                        <x-qr :payload="$qr" :size="260" label="QR code de l’abonnement, à montrer au contrôleur" />
                        <p class="text-center text-base font-medium text-slate-700">Montrez ce code au contrôleur.</p>
                    @else
                        <p class="py-6 text-center text-base text-slate-600">Cet abonnement n’est plus utilisable.</p>
                    @endif
                </div>
            </x-slot:stub>
        </x-ticket>

        @can('renew', $subscription)
            @if ($subscription->stop_id)
                <x-button :href="route('espace.purchase.formula', ['stop' => $subscription->stop_id, 'formule' => $subscription->formula->value])" size="lg" block icon="refresh-cw">Acheter le mois suivant</x-button>
            @endif
        @endcan

        @if ($usable)
            <x-button :href="route('espace.subscriptions.pdf', $subscription->uuid)" variant="secondary" block icon="download">Télécharger (PDF)</x-button>
        @endif

        <p class="text-center text-sm text-slate-500">Prix payé <x-money :amount="$subscription->price_paid" /> · {{ $subscription->channel->label() }}</p>
    </div>
</x-layouts.espace>
