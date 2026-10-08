<x-layouts.admin :title="$subscriber ? 'Modifier le client' : 'Nouveau client'">
    <div class="max-w-2xl space-y-6">
        <x-page-header
            :title="$subscriber ? $subscriber->full_name : 'Nouveau client'"
            :back="$subscriber ? route('admin.subscribers.show', $subscriber) : route('admin.subscribers.index')"
        />

        @error('subscriber')
            <x-alert variant="danger">{{ $message }}</x-alert>
        @enderror

        <x-card>
            @include('guichet.subscribers._form', [
                'action' => $subscriber ? route('admin.subscribers.update', $subscriber) : route('admin.subscribers.store'),
                'then' => null,
                'model' => $subscriber,
                'accountForPhone' => $accountForPhone ?? null,
                'submitLabel' => 'Enregistrer le client',
            ])
        </x-card>

        @if ($subscriber)
            @can('delete', $subscriber)
                <x-delete-card name="delete-subscriber" :action="route('admin.subscribers.destroy', $subscriber)" label="Supprimer le client" :title="'Supprimer '.$subscriber->full_name.' ?'">
                    Sa photo est effacée. S’il a déjà payé, ses reçus restent dans l’historique. Impossible tant qu’un abonnement est en cours.
                </x-delete-card>
            @endcan
        @endif
    </div>
</x-layouts.admin>
