<x-layouts.admin title="Clients">
    <div class="space-y-6">
        <x-page-header title="Clients" :subtitle="$total.' client(s) enregistré(s). Cherchez par nom, téléphone ou N° de reçu.'">
            @can('create', App\Models\Subscriber::class)
                <x-slot:actions>
                    <x-button :href="route('admin.subscribers.import')" variant="secondary" icon="file-spreadsheet">Importer (Excel)</x-button>
                    <x-button :href="route('admin.subscribers.create')" icon="user-plus">Nouveau client</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        @error('subscriber')
            <x-alert variant="danger">{{ $message }}</x-alert>
        @enderror

        <x-card>
            <form method="GET" action="{{ route('admin.subscribers.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end" data-no-loading>
                <x-input name="q" label="Rechercher" icon="search" :value="$filters['q']" placeholder="Nom, téléphone, N° de reçu" />
                <x-select name="zone" label="Zone" :options="$zoneOptions" :value="$filters['zone'] ?? ''" />
                <x-select name="statut" label="Statut" :options="['' => 'Tous', 'abonne' => 'Abonnement en cours', 'sans' => 'Sans abonnement en cours', 'photo' => 'Photo manquante', 'archive' => 'Supprimés (archives)']" :value="$filters['statut'] ?? ''" />
                <x-button type="submit" variant="secondary" icon="filter">Filtrer</x-button>
            </form>
        </x-card>

        <x-table caption="Clients" empty="Aucun client pour ces critères.">
            <x-slot:head>
                <th>Nom</th><th>Téléphone</th><th>Point de montée</th><th>Abonnement en cours</th><th class="text-right">Abonnements</th><th><span class="sr-only">Actions</span></th>
            </x-slot:head>
            @foreach ($subscribers as $subscriber)
                @php $running = $subscriber->subscriptions->first(); @endphp
                <tr>
                    <td class="font-medium">
                        <a href="{{ route('admin.subscribers.show', $subscriber) }}" class="text-primary-700 hover:underline">{{ $subscriber->full_name }}</a>
                        @unless ($subscriber->hasPhoto() || $subscriber->trashed())
                            <span class="block text-xs font-normal text-warning-700">Photo manquante</span>
                        @endunless
                    </td>
                    <td class="whitespace-nowrap tabular-nums">{{ $subscriber->formatted_phone }}</td>
                    <td>{{ $subscriber->stop?->name ?? '—' }} <span class="block text-xs text-slate-500">{{ $subscriber->stop?->zone->name }}</span></td>
                    <td>
                        @if ($subscriber->trashed())
                            <x-badge icon="archive">Supprimé</x-badge>
                        @elseif ($running)
                            <x-badge variant="success" icon="circle-check">{{ $running->formula->shortLabel() }} · {{ ucfirst($running->monthLabel()) }}</x-badge>
                        @else
                            <x-badge>Aucun</x-badge>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $subscriber->subscriptions_count }}</td>
                    <td class="whitespace-nowrap text-right">
                        <x-button :href="route('admin.subscribers.show', $subscriber)" variant="secondary" size="sm" icon-right="chevron-right">Ouvrir</x-button>
                        @unless ($subscriber->trashed())
                            @can('update', $subscriber)
                                <x-button :href="route('admin.subscribers.edit', $subscriber)" variant="ghost" size="sm" icon="pencil">Modifier</x-button>
                            @endcan
                            @can('delete', $subscriber)
                                <x-button variant="ghost" size="sm" icon="trash-2" data-modal-open="delete-subscriber-{{ $subscriber->id }}">Supprimer</x-button>
                            @endcan
                        @endunless
                    </td>
                </tr>
            @endforeach
            @if ($subscribers->hasPages())
                <x-slot:footer>{{ $subscribers->links() }}</x-slot:footer>
            @endif
        </x-table>

        @foreach ($subscribers as $subscriber)
            @if (! $subscriber->trashed())
                @can('delete', $subscriber)
                    <x-confirm :name="'delete-subscriber-'.$subscriber->id" :title="'Supprimer '.$subscriber->full_name.' ?'" :action="route('admin.subscribers.destroy', $subscriber)" method="DELETE" confirm-label="Oui, supprimer le client">
                        Sa photo est effacée. S’il a déjà payé, ses reçus restent dans l’historique. Impossible tant qu’un abonnement est en cours.
                    </x-confirm>
                @endcan
            @endif
        @endforeach
    </div>
</x-layouts.admin>
