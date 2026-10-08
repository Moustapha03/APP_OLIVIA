<x-layouts.admin title="Rôles et permissions">
    <div class="space-y-6">
        <x-page-header title="Rôles et permissions" subtitle="Ce que chaque rôle peut faire. Pour changer le rôle d’une personne, ouvrez sa fiche dans « Utilisateurs »." />

        <x-alert variant="info">
            Les permissions sont définies par l’équipe technique (fichier <code class="text-sm">PermissionCatalog</code>) pour éviter qu’un clic ouvre un accès par erreur.
        </x-alert>

        <x-table caption="Permissions par rôle">
            <x-slot:head>
                <th>Permission</th>
                @foreach ($roles as $role)
                    <th class="text-center">{{ $role->label() }}</th>
                @endforeach
            </x-slot:head>
            @foreach ($groups as $group => $permissions)
                <tr class="bg-slate-50">
                    <td colspan="{{ count($roles) + 1 }}" class="py-2! text-sm font-semibold uppercase tracking-wide text-slate-600">{{ $group }}</td>
                </tr>
                @foreach ($permissions as $permission => $label)
                    <tr>
                        <td>
                            <span class="block">{{ $label }}</span>
                            <code class="text-xs text-slate-500">{{ $permission }}</code>
                        </td>
                        @foreach ($roles as $role)
                            <td class="text-center">
                                @if (in_array($permission, $grants[$role->value], true))
                                    <x-lucide-check class="mx-auto size-5 text-success-600" aria-label="Oui" />
                                @else
                                    <span class="text-slate-300" aria-label="Non">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </x-table>
    </div>
</x-layouts.admin>
