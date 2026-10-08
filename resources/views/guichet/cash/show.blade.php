<x-layouts.guichet title="Ma caisse">
    <x-slot:status>
        @if ($cashSession)
            <x-badge variant="success" icon="lock-open" size="lg">Caisse ouverte</x-badge>
        @else
            <x-badge variant="warning" icon="lock" size="lg">Caisse fermée</x-badge>
        @endif
    </x-slot:status>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-page-header title="Ma caisse" />

        @if (! $cashSession)
            <x-card title="Ouvrir ma caisse" subtitle="Comptez l’argent qui est déjà dans le tiroir (le fond de caisse).">
                <form method="POST" action="{{ route('guichet.cash.open') }}" class="space-y-5">
                    @csrf
                    <x-input
                        name="opening_float"
                        label="Fond de caisse"
                        inputmode="numeric"
                        pattern="[0-9 .]+"
                        data-pattern-message="Uniquement des chiffres."
                        suffix="FCFA"
                        placeholder="20000"
                        hint="Indiquez 0 si le tiroir est vide."
                        required
                        autofocus
                    />
                    <x-button type="submit" size="lg" block icon="lock-open" loading-text="Ouverture…">Ouvrir la caisse</x-button>
                </form>
            </x-card>

            @if ($lastClosed)
                <p class="text-center text-sm text-slate-600">
                    Dernière clôture le {{ $lastClosed->closed_at?->format('d/m/Y à H\hi') }} ·
                    <a href="{{ route('guichet.cash.report', $lastClosed) }}" class="font-semibold text-primary-700 underline underline-offset-4">voir le rapport</a>
                </p>
            @endif
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Fond de caisse" icon="wallet"><x-money :amount="$totals->openingFloat" /></x-stat>
                <x-stat label="Encaissé en espèces" icon="banknote" :hint="$totals->salesCount.' vente(s) au total'"><x-money :amount="$totals->cashAmount" /></x-stat>
                <x-stat label="Doit être dans le tiroir" icon="calculator" variant="success"><x-money :amount="$totals->expected()" /></x-stat>
            </div>

            @if ($totals->transferAmount > 0)
                <x-alert variant="info" title="Mobile Money reçu : {{ \App\Support\Money::format($totals->transferAmount) }}">
                    {{ collect($totals->transfersByMethod)->map(fn ($v, $k) => $k.' '.\App\Support\Money::format($v))->join(' · ') }}. Cet argent n’est pas dans le tiroir : il est sur le compte de la compagnie.
                </x-alert>
            @endif

            @if ($totals->cancelledCount > 0)
                <p class="text-sm text-slate-600">{{ $totals->cancelledCount }} vente(s) annulée(s) et remboursée(s) : <x-money :amount="$totals->cancelledAmount" />, déjà déduites.</p>
            @endif

            <x-button :href="route('guichet.subscriptions.create')" size="lg" block icon="banknote">Continuer à vendre</x-button>

            <x-card title="Clôturer ma caisse" subtitle="En fin de service : comptez tout l’argent du tiroir.">
                <form method="POST" action="{{ route('guichet.cash.close') }}" class="space-y-5">
                    @csrf
                    <x-input
                        name="counted_amount"
                        label="Espèces comptées dans le tiroir"
                        inputmode="numeric"
                        pattern="[0-9 .]+"
                        data-pattern-message="Uniquement des chiffres."
                        suffix="FCFA"
                        :hint="'Attendu : '.\App\Support\Money::format($totals->expected()).'.'"
                        required
                    />
                    <x-textarea
                        name="justification"
                        label="Explication de l’écart"
                        hint="Obligatoire s’il manque de l’argent ou s’il y en a en trop."
                        placeholder="Ex. : erreur de monnaie rendue à 10 h"
                    />
                    <x-button type="submit" variant="secondary" size="lg" block icon="lock" loading-text="Clôture…">Clôturer la caisse</x-button>
                </form>
            </x-card>
        @endif
    </div>
</x-layouts.guichet>
