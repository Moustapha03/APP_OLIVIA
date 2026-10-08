@php
    $stopOptions = collect($stops)->mapWithKeys(fn ($s) => [$s['id'] => $s['name']])->all();
@endphp

<x-layouts.guichet title="Aperçu · Guichet" :preview="true">
    <x-slot:status>
        <x-badge variant="success" icon="lock-open" size="lg">Caisse ouverte</x-badge>
    </x-slot:status>

    <x-design.preview-banner />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card title="Nouvelle vente" subtitle="Paiement en espèces">
            <form class="space-y-6" action="#">
                <x-input name="preview_phone" label="Téléphone du client" type="tel" prefix="+225" inputmode="numeric" hint="Laissez vide pour un client sans compte." />

                <x-select name="preview_stop" label="Arrêt" :options="$stopOptions" value="2" required />

                <fieldset class="space-y-3">
                    <legend class="mb-3 text-base font-medium text-slate-800">Formule</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-choice name="preview_formula" value="daily" title="Ticket du jour" description="2 trajets" :amount="1500" icon="ticket" checked />
                        <x-choice name="preview_formula" value="monthly" title="Abonnement" description="30 jours" :amount="27000" icon="id-card" />
                    </div>
                </fieldset>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-5 py-4">
                    <span class="text-lg font-medium text-slate-700">À encaisser</span>
                    <x-money :amount="1500" class="text-3xl font-bold" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-button size="lg" variant="success" icon="banknote" block>Encaisser</x-button>
                    <x-button size="lg" variant="secondary" icon="printer" block>Encaisser et imprimer</x-button>
                </div>
            </form>
        </x-card>

        <div class="space-y-4">
            <x-stat label="Encaissé aujourd’hui" icon="banknote"><x-money :amount="54000" /></x-stat>
            <x-stat label="Ventes" value="36" icon="ticket" />
            <x-card title="Dernières ventes" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @foreach (array_slice($sales, 0, 3) as $sale)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $sale['customer'] }}</p>
                                <p class="text-sm text-slate-600">{{ $sale['time'] }} · {{ $sale['stop'] }}</p>
                            </div>
                            <x-money :amount="$sale['amount']" class="font-semibold" />
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.guichet>
