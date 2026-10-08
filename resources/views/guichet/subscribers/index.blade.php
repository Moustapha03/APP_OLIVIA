<x-layouts.guichet title="Abonnés">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header title="Abonnés">
            @can('customers.create')
                <x-slot:actions>
                    <x-button icon="user-plus" data-modal-open="new-subscriber">Nouvel abonné</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <x-card>
            <form method="GET" action="{{ route('guichet.subscribers.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-no-loading>
                <x-input name="q" label="Rechercher un abonné" icon="search" :value="$search" placeholder="Nom ou téléphone" class="sm:min-w-80" autofocus />
                <x-button type="submit" variant="secondary" icon="search">Rechercher</x-button>
            </form>
        </x-card>

        @if ($search !== '')
            @forelse ($subscribers as $subscriber)
                <x-card>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        @if ($subscriber->hasPhoto())
                            <img src="{{ route('photos.subscriber', $subscriber) }}" alt="Photo de {{ $subscriber->full_name }}" class="size-16 rounded-xl object-cover">
                        @else
                            <span class="flex size-16 items-center justify-center rounded-xl bg-slate-100 text-slate-400" title="Pas de photo"><x-lucide-user-round class="size-8" aria-hidden="true" /></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold">{{ $subscriber->full_name }}</p>
                            <p class="text-sm text-slate-600">{{ $subscriber->formatted_phone ?: 'Sans téléphone' }} · {{ $subscriber->stop?->name }} · {{ $subscriber->stop?->zone?->name }}</p>
                            @forelse ($subscriber->subscriptions as $running)
                                <p class="text-sm font-medium text-success-700">{{ $running->formula->shortLabel() }} · {{ $running->monthLabel() }}</p>
                            @empty
                                <p class="text-sm text-slate-500">Pas d’abonnement en cours</p>
                            @endforelse
                            @unless ($subscriber->hasPhoto())
                                <x-badge variant="warning" icon="camera">Photo manquante</x-badge>
                            @endunless
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (auth()->user()->openCashSession()->exists())
                                <x-button :href="route('guichet.subscriptions.create', ['abonne' => $subscriber->id])" size="sm" icon="id-card">Abonner</x-button>
                            @endif
                            @can('update', $subscriber)
                                <x-button :href="route('guichet.subscribers.edit', $subscriber)" variant="secondary" size="sm" icon="pencil">Modifier</x-button>
                            @endcan
                        </div>
                    </div>
                </x-card>
            @empty
                <x-empty-state icon="user-round" title="Aucun abonné trouvé">Vérifiez l’orthographe ou le numéro, ou créez l’abonné.</x-empty-state>
            @endforelse
        @endif
    </div>

    @can('customers.create')
        <x-modal name="new-subscriber" title="Nouvel abonné" :show="$errors->hasAny(['full_name', 'phone', 'stop_id', 'photo'])">
            @include('guichet.subscribers._form', ['action' => route('guichet.subscribers.store'), 'then' => null, 'model' => null])
        </x-modal>
    @endcan
</x-layouts.guichet>
