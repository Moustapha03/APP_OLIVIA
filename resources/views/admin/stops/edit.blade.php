<x-layouts.admin title="Modifier le point de montée">
    <div class="max-w-2xl space-y-6">
        <x-page-header :title="$stop->name" :subtitle="'Zone '.$zone->name.' · '.$line->name" :back="route('admin.lines.show', $line)" />

        <x-card>
            <form method="POST" action="{{ route('admin.lines.zones.stops.update', [$line, $zone, $stop]) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="Nom du point de montée" :value="$stop->name" required autofocus />
                <x-checkbox name="is_active" :checked="$stop->is_active">Point de montée actif</x-checkbox>

                <x-button type="submit" size="lg" block>Enregistrer</x-button>
            </form>
        </x-card>
        @can('delete', $stop)
            <x-delete-card name="delete-stop" :action="route('admin.lines.zones.stops.destroy', [$line, $zone, $stop])" label="Supprimer le point de montée" :title="'Supprimer « '.$stop->name.' » ?'">
                S’il est déjà utilisé par des abonnés ou des reçus, il est seulement archivé (plus proposé) pour garder l’historique.
            </x-delete-card>
        @endcan
    </div>
</x-layouts.admin>
