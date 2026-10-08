@php use App\Enums\FareType; @endphp

<x-layouts.admin :title="$line->name">
    <div class="space-y-6">
        <x-page-header :title="$line->name" :subtitle="'Destination : '.$line->destination_name.' · Code '.$line->code" :back="route('admin.lines.index')">
            <x-slot:actions>
                @if (! $line->is_active)
                    <x-badge icon="pause" size="lg">Ligne inactive</x-badge>
                @endif
                @can('update', $line)
                    <x-button :href="route('admin.lines.edit', $line)" variant="secondary" icon="pencil">Modifier</x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-alert variant="info" title="Zones et points de montée">
            Le prix dépend de la <strong>zone</strong> du point de montée. Les zones sont classées de la plus proche à la plus loin de {{ $line->destination_name }}.
            Un abonné d’une zone peut aussi monter dans les zones plus proches de {{ $line->destination_name }}.
        </x-alert>

        @forelse ($line->zones as $zone)
            <x-card>
                <x-slot:actions>
                    @if (! $zone->is_active)
                        <x-badge icon="pause">Inactive</x-badge>
                    @endif
                    @can('stops.manage')
                        <form method="POST" action="{{ route('admin.lines.zones.move', [$line, $zone]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" @disabled($loop->first) class="flex size-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 disabled:opacity-30" aria-label="Rapprocher la zone {{ $zone->name }} de la destination" title="Plus près de la destination">
                                <x-lucide-arrow-up class="size-5" aria-hidden="true" />
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.lines.zones.move', [$line, $zone]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" @disabled($loop->last) class="flex size-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 disabled:opacity-30" aria-label="Éloigner la zone {{ $zone->name }} de la destination" title="Plus loin de la destination">
                                <x-lucide-arrow-down class="size-5" aria-hidden="true" />
                            </button>
                        </form>
                        <x-button :href="route('admin.lines.zones.edit', [$line, $zone])" variant="ghost" size="sm" icon="pencil">Modifier</x-button>
                    @endcan
                </x-slot:actions>

                <div class="-mt-2 mb-4 flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-full bg-primary-50 font-semibold text-primary-700 tabular-nums">{{ $loop->iteration }}</span>
                    <h2 class="text-lg font-semibold">{{ $zone->name }}</h2>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    {{-- Prices of the zone --}}
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Prix en vigueur</p>
                        <dl class="divide-y divide-slate-100 rounded-2xl ring-1 ring-slate-200">
                            @foreach ($types as $type)
                                @php $fare = $currentFares->get($zone->id.'-'.$type->value); @endphp
                                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                                    <dt class="text-sm">{{ $type->shortLabel() }}</dt>
                                    <dd>
                                        @if ($fare)
                                            <x-money :amount="$fare->amount" class="font-semibold" />
                                        @else
                                            <x-badge variant="warning" icon="triangle-alert">Pas de tarif</x-badge>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                        @can('create', App\Models\Fare::class)
                            <x-button :href="route('admin.fares.create', ['zone' => $zone->id])" variant="ghost" size="sm" icon="tag" class="mt-2">Changer un prix</x-button>
                        @endcan
                    </div>

                    {{-- Boarding points --}}
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Points de montée ({{ $zone->stops->count() }})</p>
                        <ul class="flex flex-wrap gap-2">
                            @forelse ($zone->stops as $stop)
                                <li>
                                    @can('stops.manage')
                                        <a href="{{ route('admin.lines.zones.stops.edit', [$line, $zone, $stop]) }}" @class([
                                            'inline-flex min-h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-medium ring-1 ring-inset hover:bg-slate-50',
                                            'ring-slate-300 text-slate-800' => $stop->is_active,
                                            'ring-slate-200 text-slate-400 line-through' => ! $stop->is_active,
                                        ])>
                                            <x-lucide-map-pin class="size-4" aria-hidden="true" />{{ $stop->name }}
                                        </a>
                                    @else
                                        <span class="inline-flex min-h-10 items-center gap-1.5 rounded-xl px-3 text-sm ring-1 ring-inset ring-slate-300"><x-lucide-map-pin class="size-4" aria-hidden="true" />{{ $stop->name }}</span>
                                    @endcan
                                </li>
                            @empty
                                <li class="text-sm text-slate-600">Aucun point de montée.</li>
                            @endforelse
                        </ul>

                        @can('stops.manage')
                            <form method="POST" action="{{ route('admin.lines.zones.stops.store', [$line, $zone]) }}" class="mt-3 flex gap-2">
                                @csrf
                                <input type="hidden" name="is_active" value="1">
                                <label for="new-stop-{{ $zone->id }}" class="sr-only">Nouveau point de montée dans {{ $zone->name }}</label>
                                <input id="new-stop-{{ $zone->id }}" name="name" required placeholder="Nouveau point de montée" class="block min-h-11 w-full min-w-0 rounded-xl border-0 bg-white px-3 text-base ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-primary-600">
                                <x-button type="submit" variant="secondary" size="sm" icon="plus">Ajouter</x-button>
                            </form>
                        @endcan
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="map" title="Aucune zone">Ajoutez la première zone ci-dessous.</x-empty-state>
        @endforelse

        @error('name')
            <x-alert variant="danger">{{ $message }}</x-alert>
        @enderror

        @can('stops.manage')
            <x-card title="Ajouter une zone" subtitle="Elle est ajoutée comme la plus loin de la destination. Déplacez-la ensuite avec les flèches.">
                <form method="POST" action="{{ route('admin.lines.zones.store', $line) }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    @csrf
                    <input type="hidden" name="is_active" value="1">
                    <x-input name="name" label="Nom de la zone" placeholder="Bonoua / Bassam" class="sm:min-w-80" required />
                    <x-button type="submit" icon="plus">Ajouter la zone</x-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.admin>
