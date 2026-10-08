@php $pending = $payment->isPending(); @endphp

<x-layouts.espace title="Paiement">
    @if ($pending)
        {{-- Checks again every 5 s (no inline script: CSP). --}}
        <x-slot:head><meta http-equiv="refresh" content="5"></x-slot:head>
    @endif

    <div class="space-y-6">
        <x-stepper :steps="['Montée', 'Formule', 'Paiement']" :current="3" />

        @if ($pending)
            <div class="flex flex-col items-center gap-4 rounded-3xl bg-white px-6 py-10 text-center shadow-soft ring-1 ring-slate-200/70" role="status" aria-live="polite">
                <x-lucide-loader-circle class="size-14 animate-spin text-primary-600" aria-hidden="true" />
                <h1 class="text-2xl font-semibold">Vérification du paiement…</h1>
                <p class="text-base text-slate-600">
                    Si vous n’avez pas encore validé sur votre téléphone, faites-le maintenant.
                    Cette page se met à jour toute seule.
                </p>
                <p class="text-sm text-slate-500">{{ $payment->purpose->label() }}{{ $payment->subscriber ? ' · '.$payment->subscriber->full_name : '' }} · <x-money :amount="$payment->amount" /></p>
            </div>
        @else
            <div class="flex flex-col items-center gap-4 rounded-3xl bg-white px-6 py-10 text-center shadow-soft ring-1 ring-slate-200/70" role="alert">
                <span class="flex size-16 items-center justify-center rounded-full bg-danger-100 text-danger-700">
                    <x-lucide-circle-x class="size-8" aria-hidden="true" />
                </span>
                <h1 class="text-2xl font-semibold">Le paiement n’a pas abouti</h1>
                <p class="text-base text-slate-600">
                    @if ($payment->failure_reason === 'amount_mismatch')
                        Le montant reçu ne correspond pas à la commande. Ne repayez pas : présentez-vous au guichet avec la référence ci-dessous.
                    @else
                        Aucun argent n’a été prélevé pour ce titre. Vous pouvez réessayer, ou payer en espèces au guichet.
                    @endif
                </p>
                <p class="text-sm text-slate-500">Référence : <span class="font-mono">{{ $payment->reference }}</span></p>
            </div>

            <x-button :href="$payment->stop_id ? route('espace.purchase.formula', ['stop' => $payment->stop_id, 'formule' => $payment->purpose->value]) : route('espace.purchase.create')" size="lg" block icon="refresh-cw">Réessayer</x-button>
        @endif

        <x-button :href="route('espace.passes.index')" variant="secondary" block icon="qr-code">Mes titres</x-button>
    </div>
</x-layouts.espace>
