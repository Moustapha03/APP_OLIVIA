<x-layouts.admin title="Modifier la zone">
    <div class="max-w-2xl space-y-6">
        <x-page-header :title="'Zone '.$zone->name" :subtitle="$line->name" :back="route('admin.lines.show', $line)" />

        <x-card>
            <form method="POST" action="{{ route('admin.lines.zones.update', [$line, $zone]) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="Nom de la zone" :value="$zone->name" required autofocus />
                <x-checkbox name="is_active" :checked="$zone->is_active">Zone active (vendable)</x-checkbox>

                <x-alert variant="warning">
                    Une zone inactive ne peut plus être vendue. Les titres déjà vendus restent valables.
                </x-alert>

                <x-button type="submit" size="lg" block>Enregistrer</x-button>
            </form>
        </x-card>
        @can('stops.manage')
            <x-delete-card name="delete-zone" :action="route('admin.lines.zones.destroy', [$line, $zone])" label="Supprimer la zone" :title="'Supprimer la zone '.$zone->name.' ?'">
                Ses points de montée et ses prix sont supprimés aussi. Si des ventes ont déjà été faites, la zone est seulement archivée (plus vendue) pour garder l’historique.
            </x-delete-card>
        @endcan
    </div>
</x-layouts.admin>
