@php $temporary = session('temporary_password'); @endphp

<x-layouts.guichet title="Comptes clients">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header title="Comptes clients" subtitle="Accès en ligne des clients (mot de passe). Pour vendre, utilisez Abonnés.">
            @can('customers.create')
                <x-slot:actions>
                    <x-button icon="user-plus" data-modal-open="new-customer">Nouveau client</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        @if ($temporary)
            <x-temporary-password :password="$temporary['password']" :for="$temporary['for']" :phone="$temporary['phone']" />
        @endif

        <x-card>
            <form method="GET" action="{{ route('guichet.customers.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-no-loading>
                <x-input name="q" label="Rechercher un client" icon="search" :value="$search" placeholder="Téléphone ou nom" class="sm:min-w-80" autofocus />
                <x-button type="submit" variant="secondary" icon="search">Rechercher</x-button>
            </form>
        </x-card>

        @if ($search !== '')
            @forelse ($customers as $customer)
                <x-card>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <span class="flex size-16 items-center justify-center rounded-xl bg-slate-100 text-slate-400"><x-lucide-user-round class="size-8" aria-hidden="true" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold">{{ $customer->name }}</p>
                            <p class="text-sm text-slate-600">
                                {{ $customer->formatted_phone }}
                                · {{ $customer->subscribers_count }} abonné(s) rattaché(s)
                                @if ($customer->isLocked()) · <span class="font-semibold text-warning-700">compte bloqué</span> @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-button :href="route('guichet.subscribers.index', ['q' => $customer->formatted_phone])" size="sm" icon="users">Abonnés de ce numéro</x-button>
                            @can('resetPassword', $customer)
                                <x-button variant="secondary" size="sm" icon="key-round" data-modal-open="reset-{{ $customer->id }}">Mot de passe oublié</x-button>
                            @endcan
                        </div>
                    </div>
                </x-card>

                @can('resetPassword', $customer)
                    <x-confirm
                        :name="'reset-'.$customer->id"
                        title="Donner un mot de passe provisoire ?"
                        :action="route('guichet.customers.password.reset', $customer)"
                        confirm-label="Oui, j’ai vérifié sa pièce d’identité"
                        variant="warning"
                    >
                        Vérifiez d’abord la pièce d’identité de {{ $customer->name }}. Son ancien mot de passe ne marchera plus.
                    </x-confirm>
                @endcan
            @empty
                <x-empty-state icon="user-round" title="Aucun client trouvé">
                    Vérifiez le numéro, ou créez le client.
                </x-empty-state>
            @endforelse
        @endif
    </div>

    @can('customers.create')
        <x-modal name="new-customer" title="Nouveau client" :show="$errors->hasAny(['first_name', 'last_name', 'phone', 'privacy'])">
            <form method="POST" action="{{ route('guichet.customers.store') }}" class="space-y-5">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="first_name" label="Prénom" required />
                    <x-input name="last_name" label="Nom" required />
                </div>
                <x-input name="phone" label="Téléphone" type="tel" prefix="+225" inputmode="numeric" :value="$prefillPhone" required />
                <x-checkbox name="privacy" required>Le client accepte que ses données servent uniquement à gérer ses titres de transport (loi n° 2013-450).</x-checkbox>
                <x-button type="submit" size="lg" block icon="user-plus" loading-text="Création…">Créer le client</x-button>
            </form>
        </x-modal>
    @endcan
</x-layouts.guichet>
