<x-layouts.admin title="Tarifs">
    <div class="space-y-8">
        <x-page-header title="Grille tarifaire" subtitle="Un prix par zone et par formule. Modifiez un prix tout de suite, ou programmez-le à partir d’une date.">
            @can('create', App\Models\Fare::class)
                <x-slot:actions>
                    <x-button :href="route('admin.fares.create')" icon="calendar-plus">Programmer un nouveau prix</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <x-search-bar :action="route('admin.fares.index')" :value="$search" placeholder="Zone ou point de montée (ex. Marcory)" />

        @error('fare')
            <x-alert variant="danger">{{ $message }}</x-alert>
        @enderror

        <x-alert variant="info">
            Les titres déjà vendus gardent le prix payé. Les abonnements en cours ne changent pas.
        </x-alert>

        @forelse ($lines as $line)
            <section class="space-y-4" aria-labelledby="line-{{ $line->id }}">
                <h2 id="line-{{ $line->id }}" class="text-xl font-semibold text-slate-900">{{ $line->name }}</h2>

                @forelse ($line->zones as $zone)
                    <x-card :title="$zone->name" :subtitle="$zone->stops->pluck('name')->join(', ')">
                        @unless ($zone->is_active)
                            <x-slot:actions><x-badge icon="pause">Zone inactive</x-badge></x-slot:actions>
                        @endunless

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($types as $type)
                                @php
                                    $fares = $zone->fares->where('type', $type);
                                    $current = $fares->first(fn ($f) => $f->effective_from->lte($today) && ($f->effective_to === null || $f->effective_to->gte($today)));
                                    $scheduled = $fares->first(fn ($f) => $f->effective_from->gt($today));
                                    $history = $fares->filter(fn ($f) => $f->effective_to !== null && $f->effective_to->lt($today));
                                @endphp

                                <div class="space-y-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
                                    <p class="font-semibold text-slate-800">{{ $type->shortLabel() }}</p>

                                    @if ($current)
                                        <div>
                                            <p class="text-2xl font-semibold tracking-tight"><x-money :amount="$current->amount" /></p>
                                            <p class="text-xs text-slate-600">depuis le {{ $current->effective_from->format('d/m/Y') }}</p>
                                        </div>
                                        @can('update', $current)
                                            <div class="flex flex-wrap gap-1">
                                                <x-button variant="ghost" size="sm" icon="pencil" data-modal-open="edit-fare-{{ $current->id }}">Modifier</x-button>
                                                <x-button variant="ghost" size="sm" icon="trash-2" data-modal-open="delete-fare-{{ $current->id }}">Supprimer</x-button>
                                            </div>

                                            <x-modal name="edit-fare-{{ $current->id }}" :title="$type->shortLabel().' · '.$zone->name" :show="$errors->has('amount') && (int) old('fare_id') === $current->id">
                                                <form method="POST" action="{{ route('admin.fares.update', $current) }}" class="space-y-5">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="fare_id" value="{{ $current->id }}">
                                                    <x-input name="amount" :id="'amount-'.$current->id" label="Nouveau prix" inputmode="numeric" suffix="FCFA" :value="(int) old('fare_id') === $current->id ? old('amount') : $current->amount" required />
                                                    <x-alert variant="info">S’applique aux prochaines ventes. Les reçus déjà faits gardent leur montant.</x-alert>
                                                    <x-button type="submit" size="lg" block>Enregistrer le prix</x-button>
                                                </form>
                                            </x-modal>

                                            <x-confirm
                                                name="delete-fare-{{ $current->id }}"
                                                title="Supprimer ce prix ?"
                                                :action="route('admin.fares.destroy', $current)"
                                                method="DELETE"
                                                confirm-label="Oui, supprimer ce prix"
                                            >
                                                {{ $type->shortLabel() }} · {{ $zone->name }} à {{ \App\Support\Money::format($current->amount) }}.
                                                La formule ne sera plus vendue dans cette zone tant qu’un nouveau prix n’est pas fixé. Les reçus déjà faits ne changent pas.
                                            </x-confirm>
                                        @endcan
                                    @else
                                        <x-badge variant="warning" icon="triangle-alert">Pas de prix : non vendable</x-badge>
                                    @endif

                                    @if ($scheduled)
                                        <div class="space-y-2 rounded-xl bg-white p-3 text-sm ring-1 ring-inset ring-primary-200">
                                            <p><x-badge variant="primary" icon="calendar-clock">Programmé</x-badge></p>
                                            <p><span class="font-semibold"><x-money :amount="$scheduled->amount" /></span> à partir du {{ $scheduled->effective_from->format('d/m/Y') }}</p>
                                            @can('delete', $scheduled)
                                                <x-button variant="ghost" size="sm" icon="x" data-modal-open="cancel-fare-{{ $scheduled->id }}">Annuler</x-button>
                                                <x-confirm
                                                    name="cancel-fare-{{ $scheduled->id }}"
                                                    title="Annuler ce prix programmé ?"
                                                    :action="route('admin.fares.destroy', $scheduled)"
                                                    method="DELETE"
                                                    confirm-label="Oui, annuler ce prix"
                                                    variant="warning"
                                                >
                                                    {{ $type->shortLabel() }} · {{ $zone->name }} à {{ \App\Support\Money::format($scheduled->amount) }}
                                                    à partir du {{ $scheduled->effective_from->translatedFormat('j F Y') }}. Le prix actuel restera en vigueur.
                                                </x-confirm>
                                            @endcan
                                        </div>
                                    @endif

                                    @if ($history->isNotEmpty())
                                        <details class="text-xs">
                                            <summary class="cursor-pointer font-medium text-primary-700">Anciens prix ({{ $history->count() }})</summary>
                                            <ul class="mt-2 space-y-1 text-slate-600">
                                                @foreach ($history as $old)
                                                    <li class="flex justify-between gap-2">
                                                        <span>{{ $old->effective_from->format('d/m/y') }} → {{ $old->effective_to?->format('d/m/y') }}</span>
                                                        <x-money :amount="$old->amount" />
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif

                                    @can('create', App\Models\Fare::class)
                                        <x-button :href="route('admin.fares.create', ['zone' => $zone->id, 'type' => $type->value])" variant="ghost" size="sm" icon="calendar-plus">{{ $current ? 'Programmer un prix' : 'Fixer un prix' }}</x-button>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @empty
                    <x-empty-state icon="map" title="Aucune zone sur cette ligne">Ajoutez des zones depuis la page de la ligne.</x-empty-state>
                @endforelse
            </section>
        @empty
            <x-empty-state icon="route" title="Aucune ligne">Créez d’abord une ligne et ses zones.</x-empty-state>
        @endforelse
    </div>
</x-layouts.admin>
