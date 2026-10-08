@php $editing = $vehicle->exists; @endphp

<x-layouts.admin :title="$editing ? 'Modifier le véhicule' : 'Nouveau véhicule'">
    <div class="max-w-2xl space-y-6">
        <x-page-header :title="$editing ? 'Modifier '.$vehicle->name : 'Nouveau véhicule'" :back="route('admin.vehicles.index')" />

        <x-card>
            <form method="POST" action="{{ $editing ? route('admin.vehicles.update', $vehicle) : route('admin.vehicles.store') }}" class="space-y-5">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="name" label="Nom" :value="$vehicle->name" placeholder="Bus 12" required autofocus />
                    <x-input name="registration" label="Immatriculation" :value="$vehicle->registration" placeholder="1234 AB 01" required />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="capacity" label="Nombre de places" type="number" inputmode="numeric" min="1" max="200" :value="$vehicle->capacity" required />
                    <x-select name="line_id" label="Ligne habituelle" :options="$lineOptions" :value="$vehicle->line_id" />
                </div>
                <x-textarea name="notes" label="Notes" :value="$vehicle->notes" placeholder="Révision prévue, particularités…" />
                <x-checkbox name="is_active" :checked="$vehicle->is_active">En service</x-checkbox>

                <x-button type="submit" size="lg" block>{{ $editing ? 'Enregistrer' : 'Ajouter le véhicule' }}</x-button>
            </form>
        </x-card>
        @if ($editing)
            @can('delete', $vehicle)
                <x-delete-card name="delete-vehicle" :action="route('admin.vehicles.destroy', $vehicle)" label="Supprimer le véhicule" :title="'Supprimer '.$vehicle->name.' ?'">
                    Le véhicule disparaît de la liste. Pour une immobilisation temporaire, décochez plutôt « En service ».
                </x-delete-card>
            @endcan
        @endif
    </div>
</x-layouts.admin>
