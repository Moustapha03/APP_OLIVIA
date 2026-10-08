<x-layouts.admin title="Utilisateurs">
    <div class="space-y-6">
        <x-page-header title="Utilisateurs" subtitle="Personnel et clients.">
            @can('create', App\Models\User::class)
                <x-slot:actions>
                    <x-button :href="route('admin.users.create')" icon="user-plus">Créer un compte</x-button>
                </x-slot:actions>
            @endcan
        </x-page-header>

        <x-card>
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end" data-no-loading>
                <x-input name="q" label="Rechercher" icon="search" :value="$filters['q'] ?? null" placeholder="Nom, téléphone ou e-mail" />
                <x-select
                    name="role"
                    label="Rôle"
                    :options="['' => 'Tous les rôles'] + collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()"
                    :value="$filters['role'] ?? ''"
                />
                <x-select name="status" label="Statut" :options="['' => 'Tous', 'active' => 'Actifs', 'disabled' => 'Désactivés']" :value="$filters['status'] ?? ''" />
                <x-button type="submit" variant="secondary" icon="filter">Filtrer</x-button>
            </form>
        </x-card>

        <x-table caption="Utilisateurs" empty="Aucun compte ne correspond à ces critères.">
            <x-slot:head>
                <th>Nom</th><th>Téléphone</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th><span class="sr-only">Actions</span></th>
            </x-slot:head>
            @foreach ($users as $user)
                @php $role = App\Enums\RoleName::tryFrom((string) $user->roles->first()?->name); @endphp
                <tr>
                    <td class="font-medium">{{ $user->name }}</td>
                    <td class="tabular-nums whitespace-nowrap">{{ $user->formatted_phone }}</td>
                    <td>{{ $role?->label() ?? '—' }}</td>
                    <td>
                        @if (! $user->isActive())
                            <x-badge variant="danger" icon="ban">Désactivé</x-badge>
                        @elseif ($user->isLocked())
                            <x-badge variant="warning" icon="lock">Bloqué</x-badge>
                        @else
                            <x-badge variant="success">Actif</x-badge>
                        @endif
                    </td>
                    <td class="text-sm text-slate-600">{{ $user->last_login_at?->diffForHumans() ?? 'Jamais' }}</td>
                    <td class="text-right">
                        @can('update', $user)
                            <x-button :href="route('admin.users.edit', $user)" variant="ghost" size="sm" icon="pencil">Modifier</x-button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            @if ($users->hasPages())
                <x-slot:footer>{{ $users->links() }}</x-slot:footer>
            @endif
        </x-table>
    </div>
</x-layouts.admin>
