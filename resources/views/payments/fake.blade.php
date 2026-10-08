{{-- Mobile Money simulator (driver "fake", development only). --}}
<x-layouts.base title="Paiement (simulation)" body-class="bg-slate-100 text-slate-900">
    <main id="main" class="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 px-4 py-10">
        <x-alert variant="warning" title="Simulation de paiement">
            Mode développement : aucun argent n’est prélevé. En production, cette page est celle de l’opérateur Mobile Money.
        </x-alert>

        <x-card>
            <div class="space-y-1 text-center">
                <p class="text-sm font-medium text-slate-600">{{ $payment->purpose->label() }} · {{ $payment->zone->name }}</p>
                <p class="text-4xl font-semibold"><x-money :amount="$payment->amount" /></p>
                <p class="font-mono text-xs text-slate-500">{{ $payment->reference }}</p>
            </div>

            <form method="POST" action="{{ $decideUrl }}" class="mt-6 space-y-3">
                @csrf
                <x-button type="submit" name="decision" value="accept" variant="success" size="lg" block icon="check">Valider le paiement</x-button>
                <x-button type="submit" name="decision" value="refuse" variant="secondary" size="lg" block icon="x">Refuser (solde insuffisant)</x-button>
            </form>
        </x-card>
    </main>
</x-layouts.base>
