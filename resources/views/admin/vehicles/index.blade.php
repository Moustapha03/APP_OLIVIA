<x-layouts.admin title="Véhicules">
    <div class="space-y-6">
        <x-page-header title="Véhicules">
            @can('create', App\Models\Vehicle::class)
                <x-slot:actions>
                    <x-button :href="route('admin.vehicles.create')" icon="plus">Ajouter un véhicule</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <x-search-bar :action="route('admin.vehicles.index')" :value="$search" placeholder="Nom ou immatriculation">
            <div class="sm:w-56"><x-select name="statut" label="Statut" :options="['' => 'Tous', 'service' => 'En service', 'hors-service' => 'Hors service']" :value="$status" /></div>
        </x-search-bar>

        <x-table caption="Véhicules" empty="Aucun véhicule enregistré.">
            <x-slot:head>
                <th>Nom</th><th>Immatriculation</th><th class="text-right">Places</th><th>Ligne</th><th>Statut</th><th><span class="sr-only">Actions</span></th>
            </x-slot:head>
            @foreach ($vehicles as $vehicle)
                <tr>
                    <td class="font-medium">{{ $vehicle->name }}</td>
                    <td class="font-mono text-sm">{{ $vehicle->registration }}</td>
                    <td class="text-right tabular-nums">{{ $vehicle->capacity }}</td>
                    <td>{{ $vehicle->line?->name ?? '—' }}</td>
                    <td>
                        @if ($vehicle->is_active)
                            <x-badge variant="success" icon="circle-check">En service</x-badge>
                        @else
                            <x-badge icon="wrench">Hors service</x-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @can('update', $vehicle)
                            <x-button :href="route('admin.vehicles.edit', $vehicle)" variant="ghost" size="sm" icon="pencil">Modifier</x-button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-layouts.admin>
