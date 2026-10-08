<x-layouts.admin title="Lignes et zones">
    <div class="space-y-6">
        <x-page-header title="Lignes, zones et points de montée" subtitle="Le prix dépend de la zone du point de montée.">
            @can('create', App\Models\Line::class)
                <x-slot:actions>
                    <x-button :href="route('admin.lines.create')" icon="plus">Nouvelle ligne</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <x-search-bar :action="route('admin.lines.index')" :value="$search" placeholder="Ligne, zone ou point de montée (ex. Zouglou)" />

        <x-table caption="Lignes" :empty="$search !== '' ? 'Aucune ligne ne correspond à « '.$search.' ».' : 'Aucune ligne pour le moment. Créez la première.'">
            <x-slot:head>
                <th>Code</th><th>Ligne</th><th>Destination</th><th class="text-right">Zones</th><th class="text-right">Points</th><th class="text-right">Véhicules</th><th>Statut</th><th><span class="sr-only">Actions</span></th>
            </x-slot:head>
            @foreach ($lines as $line)
                <tr>
                    <td class="font-mono text-sm">{{ $line->code }}</td>
                    <td class="font-medium">{{ $line->name }}</td>
                    <td>{{ $line->destination_name }}</td>
                    <td class="text-right tabular-nums">{{ $line->zones_count }}</td>
                    <td class="text-right tabular-nums">{{ $line->stops_count }}</td>
                    <td class="text-right tabular-nums">{{ $line->vehicles_count }}</td>
                    <td>
                        @if ($line->is_active)
                            <x-badge variant="success" icon="circle-check">Active</x-badge>
                        @else
                            <x-badge icon="pause">Inactive</x-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-button :href="route('admin.lines.show', $line)" variant="secondary" size="sm" icon-right="chevron-right">Ouvrir</x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-layouts.admin>
